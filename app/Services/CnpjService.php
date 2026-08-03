<?php

namespace App\Services;

use App\Core\Logger;

/**
 * Servico para consulta de CNPJ com fallback automatico.
 * Provedores:
 * 1. BrasilAPI (Gratuito, sem token, rate limit generoso)
 * 2. ReceitaWS (Gratuito, sem token, rate limit restrito de 3 req/min)
 * 3. CNPJa (Pago, requer token, mas e o mais confiavel)
 */
class CnpjService {
    
    /**
     * Consulta o CNPJ tentando os provedores em ordem.
     * Retorna array normalizado com os dados ou lanca exception.
     */
    public function consultar(string $cnpj): array {
        // Limpa a string: mantem apenas numeros
        $cnpj = preg_replace('/[^0-9]/', '', $cnpj);
        
        if (strlen($cnpj) !== 14) {
            throw new \InvalidArgumentException('CNPJ invalido: deve conter 14 digitos.');
        }

        // Ordem dos provedores configuravel via .env (CNPJ_PROVIDERS=brasilapi,receitaws,cnpja)
        $ordemEnv   = $_ENV['CNPJ_PROVIDERS'] ?? 'brasilapi,receitaws,cnpja';
        $ordemLista = array_map('trim', explode(',', $ordemEnv));

        $disponiveis = [
            'brasilapi' => fn() => $this->consultarBrasilApi($cnpj),
            'receitaws' => fn() => $this->consultarReceitaWs($cnpj),
            'cnpja'     => fn() => $this->consultarCnpja($cnpj)
        ];

        // Monta a lista final respeitando a ordem do .env e ignorando nomes invalidos
        $provedores = [];
        foreach ($ordemLista as $nome) {
            if (isset($disponiveis[$nome])) {
                $provedores[$nome] = $disponiveis[$nome];
            }
        }

        $erros = [];

        foreach ($provedores as $nome => $metodo) {
            try {
                $resposta = $metodo();
                if ($resposta) {
                    Logger::info("CNPJ {$cnpj} consultado com sucesso no provedor: {$nome}");
                    return [
                        'provider'     => $nome,
                        'payload'      => $this->normalizarResposta($nome, $resposta),
                        'raw_response' => $resposta
                    ];
                }
            } catch (\Exception $e) {
                Logger::warning("Falha ao consultar CNPJ {$cnpj} no provedor {$nome}: " . $e->getMessage());
                $erros[$nome] = $e->getMessage();
                // Continua para o proximo provedor
            }
        }

        // Se chegou aqui, todos falharam
        Logger::error("Todos os provedores de CNPJ falharam para {$cnpj}", $erros);
        throw new \RuntimeException('Nao foi possivel consultar o CNPJ no momento. Tente novamente mais tarde.');
    }

    /**
     * Faz a requisicao HTTP com cURL (sem dependencias externas como Guzzle).
     */
    private function fetch(string $url, array $headers = []): array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10, // Timeout de 10 segundos para nao travar o servidor
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
            CURLOPT_SSL_VERIFYPEER => false // Em hospedagem compartilhada, o CA bundle pode estar desatualizado
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($error) {
            throw new \Exception("cURL Error: {$error}");
        }

        if ($httpCode >= 400) {
            if ($httpCode === 404) {
                throw new \Exception("CNPJ nao encontrado (404).");
            }
            if ($httpCode === 429) {
                throw new \Exception("Rate limit excedido (429).");
            }
            throw new \Exception("Erro HTTP {$httpCode}: {$response}");
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception("Resposta JSON invalida: " . json_last_error_msg());
        }

        return $data;
    }

    // =========================================================================
    // PROVEDORES
    // =========================================================================

    private function consultarBrasilApi(string $cnpj): array {
        return $this->fetch("https://brasilapi.com.br/api/cnpj/v1/{$cnpj}");
    }

    private function consultarReceitaWs(string $cnpj): array {
        $data = $this->fetch("https://www.receitaws.com.br/v1/cnpj/{$cnpj}");
        if (isset($data['status']) && $data['status'] === 'ERROR') {
            throw new \Exception("ReceitaWS retornou erro: " . ($data['message'] ?? 'Desconhecido'));
        }
        return $data;
    }

    private function consultarCnpja(string $cnpj): array {
        $token = $_ENV['CNPJA_TOKEN'] ?? null;
        if (!$token) {
            throw new \Exception("Token do CNPJa nao configurado no .env (CNPJA_TOKEN).");
        }
        return $this->fetch("https://api.cnpja.com/office/{$cnpj}", [
            "Authorization: {$token}"
        ]);
    }

    // =========================================================================
    // NORMALIZACAO
    // =========================================================================

    /**
     * Cada API retorna um formato diferente.
     * Esta funcao padroniza a saida para que o Frontend receba sempre a mesma estrutura,
     * independente de qual API funcionou.
     */
    private function normalizarResposta(string $provider, array $data): array {
        $padrao = [
            'cnpj'              => '',
            'razao_social'      => '',
            'nome_fantasia'     => '',
            'situacao_cadastral'=> '',
            'data_abertura'     => '',
            'cnae_principal'    => '',
            'cep'               => '',
            'logradouro'        => '',
            'numero'            => '',
            'complemento'       => '',
            'bairro'            => '',
            'municipio'         => '',
            'uf'                => '',
            'telefone'          => '',
            'email'             => ''
        ];

        switch ($provider) {
            case 'brasilapi':
                $padrao['cnpj']               = $data['cnpj'] ?? '';
                $padrao['razao_social']       = $data['razao_social'] ?? '';
                $padrao['nome_fantasia']      = $data['nome_fantasia'] ?? '';
                $padrao['situacao_cadastral'] = $data['descricao_situacao_cadastral'] ?? '';
                $padrao['data_abertura']      = $data['data_inicio_atividade'] ?? '';
                $padrao['cnae_principal']     = $data['cnae_fiscal_descricao'] ?? '';
                $padrao['cep']                = $data['cep'] ?? '';
                $padrao['logradouro']         = $data['logradouro'] ?? '';
                $padrao['numero']             = $data['numero'] ?? '';
                $padrao['complemento']        = $data['complemento'] ?? '';
                $padrao['bairro']             = $data['bairro'] ?? '';
                $padrao['municipio']          = $data['municipio'] ?? '';
                $padrao['uf']                 = $data['uf'] ?? '';
                $padrao['telefone']           = $data['ddd_telefone_1'] ?? '';
                $padrao['email']              = $data['email'] ?? '';
                break;

            case 'receitaws':
                $padrao['cnpj']               = preg_replace('/[^0-9]/', '', $data['cnpj'] ?? '');
                $padrao['razao_social']       = $data['nome'] ?? '';
                $padrao['nome_fantasia']      = $data['fantasia'] ?? '';
                $padrao['situacao_cadastral'] = $data['situacao'] ?? '';
                $padrao['data_abertura']      = $data['abertura'] ?? '';
                $padrao['cnae_principal']     = $data['atividade_principal'][0]['text'] ?? '';
                $padrao['cep']                = preg_replace('/[^0-9]/', '', $data['cep'] ?? '');
                $padrao['logradouro']         = $data['logradouro'] ?? '';
                $padrao['numero']             = $data['numero'] ?? '';
                $padrao['complemento']        = $data['complemento'] ?? '';
                $padrao['bairro']             = $data['bairro'] ?? '';
                $padrao['municipio']          = $data['municipio'] ?? '';
                $padrao['uf']                 = $data['uf'] ?? '';
                $padrao['telefone']           = $data['telefone'] ?? '';
                $padrao['email']              = $data['email'] ?? '';
                break;

            case 'cnpja':
                $padrao['cnpj']               = preg_replace('/[^0-9]/', '', $data['taxId'] ?? '');
                $padrao['razao_social']       = $data['company']['name'] ?? '';
                $padrao['nome_fantasia']      = $data['alias'] ?? '';
                $padrao['situacao_cadastral'] = $data['status']['text'] ?? '';
                $padrao['data_abertura']      = $data['founded'] ?? '';
                $padrao['cnae_principal']     = $data['mainActivity']['text'] ?? '';
                $padrao['cep']                = preg_replace('/[^0-9]/', '', $data['address']['zip'] ?? '');
                $padrao['logradouro']         = $data['address']['street'] ?? '';
                $padrao['numero']             = $data['address']['number'] ?? '';
                $padrao['complemento']        = $data['address']['details'] ?? '';
                $padrao['bairro']             = $data['address']['district'] ?? '';
                $padrao['municipio']          = $data['address']['city'] ?? '';
                $padrao['uf']                 = $data['address']['state'] ?? '';
                $padrao['telefone']           = ($data['phones'][0]['area'] ?? '') . ($data['phones'][0]['number'] ?? '');
                $padrao['email']              = $data['emails'][0]['address'] ?? '';
                break;
        }

        return $padrao;
    }
}
