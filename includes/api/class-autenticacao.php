<?php
/**
 * Chave de acesso da API.
 *
 * Nenhuma chave fica no código. Ordem de leitura:
 * 1. constante CEDU_INSCRICOES_API_KEY no wp-config.php (recomendado);
 * 2. hash SHA-256 gravado pela tela de Configurações (a chave em si não é
 *    guardada no banco).
 * Sem nenhuma delas o endpoint recusa tudo (503).
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

/**
 * Verificação e gerenciamento da chave.
 */
final class Autenticacao {

	/** Cabeçalho HTTP com a chave. */
	public const CABECALHO = 'X-CEDU-API-KEY';

	/** Tamanho mínimo aceito para uma chave definida à mão. */
	public const TAMANHO_MINIMO = 24;

	/** Tamanho máximo aceito (evita hash de valores enormes). */
	private const TAMANHO_MAXIMO = 256;

	/**
	 * De onde vem a chave em uso: 'constante', 'opcao' ou ''.
	 */
	public static function origem(): string {
		if ( '' !== self::chave_constante() ) {
			return 'constante';
		}
		if ( '' !== self::hash_salvo() ) {
			return 'opcao';
		}
		return '';
	}

	/** Diz se há alguma chave configurada. */
	public static function configurada(): bool {
		return '' !== self::origem();
	}

	/**
	 * Confere a chave recebida (comparação em tempo constante).
	 *
	 * @param string $recebida Valor do cabeçalho.
	 */
	public static function valida( string $recebida ): bool {
		if ( '' === $recebida || strlen( $recebida ) > self::TAMANHO_MAXIMO ) {
			return false;
		}

		switch ( self::origem() ) {
			case 'constante':
				return hash_equals( self::chave_constante(), $recebida );
			case 'opcao':
				return hash_equals( self::hash_salvo(), self::hash( $recebida ) );
			default:
				return false;
		}
	}

	/**
	 * Grava uma nova chave (só o hash).
	 *
	 * @param string $chave Chave em texto puro.
	 */
	public static function salvar( string $chave ): bool {
		$chave = trim( $chave );
		if ( strlen( $chave ) < self::TAMANHO_MINIMO || strlen( $chave ) > self::TAMANHO_MAXIMO ) {
			return false;
		}
		update_option( Config::OPCAO_CHAVE_HASH, self::hash( $chave ), false );
		return true;
	}

	/**
	 * Gera, grava e devolve uma chave aleatória nova.
	 * Ela só pode ser vista neste momento.
	 */
	public static function gerar(): string {
		$chave = wp_generate_password( 48, false, false );
		self::salvar( $chave );
		return $chave;
	}

	/**
	 * Hash usado para guardar a chave.
	 *
	 * @param string $chave Chave em texto puro.
	 */
	private static function hash( string $chave ): string {
		return hash( 'sha256', $chave );
	}

	/** Chave definida no wp-config.php. */
	private static function chave_constante(): string {
		$valor = defined( Config::CONSTANTE_CHAVE ) ? constant( Config::CONSTANTE_CHAVE ) : '';
		return is_string( $valor ) ? trim( $valor ) : '';
	}

	/** Hash gravado pela tela de Configurações. */
	private static function hash_salvo(): string {
		$valor = get_option( Config::OPCAO_CHAVE_HASH, '' );
		return is_string( $valor ) ? $valor : '';
	}
}
