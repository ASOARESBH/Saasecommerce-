<?php
namespace App\Core;

abstract class Middleware {
    abstract public function handle(): void;

    /**
     * Executa uma lista de middlewares em sequencia.
     * Aceita nome de classe simples ('App\Middlewares\AuthMiddleware')
     * ou array [Classe::class, [arg1, arg2]] quando o middleware
     * precisa de parametros no construtor (ex: PermissionMiddleware).
     */
    public static function run(array $middlewares): void {
        foreach ($middlewares as $mw) {
            if (is_string($mw)) {
                (new $mw())->handle();
            } elseif (is_array($mw)) {
                [$class, $args] = $mw;
                (new $class(...(array) $args))->handle();
            }
        }
    }
}
