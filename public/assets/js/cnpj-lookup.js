/**
 * cnpj-lookup.js
 * Utilitario para consulta de CNPJ via endpoint proprio do backend.
 *
 * NUNCA chame APIs externas (BrasilAPI, ReceitaWS, etc.) diretamente
 * do navegador. Este modulo e o unico ponto de entrada para consultas
 * de CNPJ no frontend. Ele faz fetch para /api/cnpj/{cnpj}, que e
 * o proxy PHP centralizado com cache, rate-limit e fallback.
 *
 * Uso basico:
 *   import { consultarCnpj, aplicarMascaraCnpj, inicializarCampoCnpj } from './cnpj-lookup.js';
 *
 * Uso com autopreenchimento de formulario:
 *   inicializarCampoCnpj('#campo-cnpj', {
 *       onSuccess: (dados) => {
 *           document.querySelector('#razao-social').value = dados.razao_social;
 *           document.querySelector('#cep').value = dados.cep;
 *       },
 *       onError: (erro) => console.error(erro)
 *   });
 */

'use strict';

// ============================================================
// CONSULTA PRINCIPAL
// ============================================================

/**
 * Consulta um CNPJ no backend centralizado.
 *
 * @param {string} cnpj - CNPJ com ou sem formatacao (so digitos tambem funciona)
 * @returns {Promise<Object>} Dados normalizados do CNPJ
 * @throws {CnpjError} Com .code e .message em caso de erro
 */
async function consultarCnpj(cnpj) {
    const cnpjLimpo = cnpj.replace(/[^0-9]/g, '');

    if (cnpjLimpo.length !== 14) {
        throw new CnpjError('CNPJ_INVALIDO', 'CNPJ deve conter 14 digitos.');
    }

    if (!validarDigitosCnpj(cnpjLimpo)) {
        throw new CnpjError('CNPJ_INVALIDO', 'CNPJ invalido. Verifique os digitos verificadores.');
    }

    let response;
    try {
        response = await fetch(`/api/cnpj/${cnpjLimpo}`, {
            method: 'GET',
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin' // Envia o cookie de sessao (autenticacao)
        });
    } catch (networkError) {
        throw new CnpjError('REDE', 'Falha de conexao. Verifique sua internet e tente novamente.');
    }

    let body;
    try {
        body = await response.json();
    } catch {
        throw new CnpjError('RESPOSTA_INVALIDA', 'O servidor retornou uma resposta inesperada.');
    }

    if (!response.ok || !body.success) {
        const code    = body.code    || `HTTP_${response.status}`;
        const message = body.message || 'Erro desconhecido ao consultar o CNPJ.';

        if (response.status === 429) {
            throw new CnpjError('RATE_LIMIT', message, { retryAfter: body.retry_after });
        }
        if (response.status === 422) {
            throw new CnpjError('CNPJ_INVALIDO', message);
        }
        if (response.status === 503) {
            throw new CnpjError('PROVEDOR_INDISPONIVEL', message);
        }
        throw new CnpjError(code, message);
    }

    return body.data;
}

// ============================================================
// INICIALIZADOR DE CAMPO (autopreenchimento)
// ============================================================

/**
 * Inicializa um campo de CNPJ com mascara, debounce e autopreenchimento.
 *
 * @param {string|HTMLElement} seletor - Seletor CSS ou elemento do campo CNPJ
 * @param {Object}  opcoes
 * @param {Function} opcoes.onSuccess     - Callback chamado com os dados normalizados
 * @param {Function} opcoes.onError       - Callback chamado com o objeto CnpjError
 * @param {Function} [opcoes.onLoading]   - Callback chamado ao iniciar/terminar a busca (boolean)
 * @param {number}   [opcoes.debounce]    - Delay em ms apos digitar (padrao: 800ms)
 */
function inicializarCampoCnpj(seletor, opcoes = {}) {
    const campo = typeof seletor === 'string'
        ? document.querySelector(seletor)
        : seletor;

    if (!campo) {
        console.warn('[cnpj-lookup] Campo nao encontrado:', seletor);
        return;
    }

    const {
        onSuccess  = () => {},
        onError    = (e) => console.error('[cnpj-lookup]', e.message),
        onLoading  = () => {},
        debounce: debounceMs = 800
    } = opcoes;

    let timer = null;

    // Mascara em tempo real
    campo.addEventListener('input', () => {
        campo.value = aplicarMascaraCnpj(campo.value);

        clearTimeout(timer);
        const cnpjLimpo = campo.value.replace(/[^0-9]/g, '');

        if (cnpjLimpo.length < 14) return;

        timer = setTimeout(async () => {
            onLoading(true);
            try {
                const dados = await consultarCnpj(cnpjLimpo);
                onSuccess(dados);
            } catch (erro) {
                onError(erro);
            } finally {
                onLoading(false);
            }
        }, debounceMs);
    });

    // Tambem dispara ao colar (paste) ou sair do campo com CNPJ completo
    campo.addEventListener('blur', async () => {
        clearTimeout(timer);
        const cnpjLimpo = campo.value.replace(/[^0-9]/g, '');
        if (cnpjLimpo.length !== 14) return;

        onLoading(true);
        try {
            const dados = await consultarCnpj(cnpjLimpo);
            onSuccess(dados);
        } catch (erro) {
            onError(erro);
        } finally {
            onLoading(false);
        }
    });
}

// ============================================================
// MASCARA
// ============================================================

/**
 * Formata uma string de CNPJ no padrao XX.XXX.XXX/XXXX-XX.
 * Aceita entrada parcial (formata enquanto o usuario digita).
 *
 * @param {string} valor
 * @returns {string}
 */
function aplicarMascaraCnpj(valor) {
    const d = valor.replace(/[^0-9]/g, '').substring(0, 14);
    if (d.length <= 2)  return d;
    if (d.length <= 5)  return `${d.slice(0,2)}.${d.slice(2)}`;
    if (d.length <= 8)  return `${d.slice(0,2)}.${d.slice(2,5)}.${d.slice(5)}`;
    if (d.length <= 12) return `${d.slice(0,2)}.${d.slice(2,5)}.${d.slice(5,8)}/${d.slice(8)}`;
    return `${d.slice(0,2)}.${d.slice(2,5)}.${d.slice(5,8)}/${d.slice(8,12)}-${d.slice(12)}`;
}

// ============================================================
// VALIDACAO CLIENT-SIDE (digitos verificadores)
// ============================================================

/**
 * Valida matematicamente os digitos verificadores do CNPJ.
 * Evita round-trips desnecessarios ao servidor para CNPJs obviamente invalidos.
 *
 * @param {string} cnpj - Somente digitos, 14 chars
 * @returns {boolean}
 */
function validarDigitosCnpj(cnpj) {
    if (/^(\d)\1{13}$/.test(cnpj)) return false; // Todos iguais

    const calcDigito = (cnpj, tamanho) => {
        let soma = 0, pos = tamanho - 7;
        for (let i = tamanho; i >= 1; i--) {
            soma += parseInt(cnpj[tamanho - i]) * pos--;
            if (pos < 2) pos = 9;
        }
        const resto = soma % 11;
        return resto < 2 ? 0 : 11 - resto;
    };

    return parseInt(cnpj[12]) === calcDigito(cnpj, 12)
        && parseInt(cnpj[13]) === calcDigito(cnpj, 13);
}

// ============================================================
// CLASSE DE ERRO TIPADO
// ============================================================

class CnpjError extends Error {
    /**
     * @param {string} code    - Codigo maquina: CNPJ_INVALIDO | RATE_LIMIT | PROVEDOR_INDISPONIVEL | REDE | ...
     * @param {string} message - Mensagem legivel para o usuario
     * @param {Object} [extra] - Dados adicionais (ex: retryAfter)
     */
    constructor(code, message, extra = {}) {
        super(message);
        this.name  = 'CnpjError';
        this.code  = code;
        this.extra = extra;
    }
}

// ============================================================
// EXPORTS (ES Module)
// ============================================================

export { consultarCnpj, inicializarCampoCnpj, aplicarMascaraCnpj, validarDigitosCnpj, CnpjError };
