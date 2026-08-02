<?php
/**
 * Autoloader customizado, sem dependencia obrigatoria do Composer no servidor.
 * Pensado para funcionar em hospedagem compartilhada (ex: cPanel/HostGator)
 * onde nem sempre e possivel rodar `composer install` em producao.
 */
spl_autoload_register(function ($class) {
    // Prefixo do namespace do projeto
    $prefix = 'App\\';

    // Diretorio base para o prefixo do namespace
    $base_dir = __DIR__ . '/';

    // Verifica se a classe usa o prefixo do namespace
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    // Pega o nome relativo da classe
    $relative_class = substr($class, $len);

    // Substitui o separador de namespace pelo separador de diretorio
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    // Se o arquivo existir, faz o require
    if (file_exists($file)) {
        require $file;
    }
});
