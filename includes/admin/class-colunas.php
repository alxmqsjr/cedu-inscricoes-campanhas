<?php
/**
 * Colunas extras na lista nativa de Inscrições (edit.php?post_type=inscricoes).
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

/**
 * Pontuação, estado e município na lista nativa (nomes vindos de config/campos.php).
 */
final class Colunas {

	/** Valor de orderby usado para ordenar pela pontuação. */
	private const ORDEM_PONTUACAO = 'cedu_inscricoes_pontuacao';

	/** Registra os hooks. */
	public static function registrar(): void {
		add_filter( 'manage_' . Esquema::POST_TYPE . '_posts_columns', array( self::class, 'colunas' ) );
		add_action( 'manage_' . Esquema::POST_TYPE . '_posts_custom_column', array( self::class, 'valor' ), 10, 2 );
		add_filter( 'manage_edit-' . Esquema::POST_TYPE . '_sortable_columns', array( self::class, 'ordenaveis' ) );
		add_action( 'pre_get_posts', array( self::class, 'ordenar' ) );
	}

	/**
	 * Colunas mostradas: papel => rótulo.
	 *
	 * @return array<string, string>
	 */
	private static function papeis(): array {
		$papeis = array(
			'pontuacao' => __( 'Pontuação', 'cedu-inscricoes-campanhas' ),
			'estado'    => __( 'Estado', 'cedu-inscricoes-campanhas' ),
			'municipio' => __( 'Município', 'cedu-inscricoes-campanhas' ),
		);
		return array_filter( $papeis, static fn( string $rotulo, string $papel ): bool => '' !== Esquema::campo( $papel ), ARRAY_FILTER_USE_BOTH );
	}

	/**
	 * Acrescenta as colunas.
	 *
	 * @param array<string, string> $colunas Colunas atuais.
	 * @return array<string, string>
	 */
	public static function colunas( array $colunas ): array {
		foreach ( self::papeis() as $papel => $rotulo ) {
			$colunas[ 'cedu_inscricoes_' . $papel ] = $rotulo;
		}
		return $colunas;
	}

	/**
	 * Imprime o valor da coluna.
	 *
	 * @param string $coluna  Coluna.
	 * @param int    $post_id ID da inscrição.
	 */
	public static function valor( string $coluna, int $post_id ): void {
		$papel = str_replace( 'cedu_inscricoes_', '', $coluna );
		if ( isset( self::papeis()[ $papel ] ) ) {
			echo esc_html( Esquema::valor( $post_id, $papel ) );
		}
	}

	/**
	 * Pontuação ordenável.
	 *
	 * @param array<string, string> $colunas Colunas ordenáveis.
	 * @return array<string, string>
	 */
	public static function ordenaveis( array $colunas ): array {
		if ( isset( self::papeis()['pontuacao'] ) ) {
			$colunas['cedu_inscricoes_pontuacao'] = self::ORDEM_PONTUACAO;
		}
		return $colunas;
	}

	/**
	 * Ordena por pontuação na lista nativa.
	 *
	 * @param \WP_Query $query Consulta.
	 */
	public static function ordenar( \WP_Query $query ): void {
		if ( ! is_admin() || ! $query->is_main_query() || self::ORDEM_PONTUACAO !== $query->get( 'orderby' ) ) {
			return;
		}
		$meta = Esquema::campo( 'pontuacao' );
		// OR com NOT EXISTS: inscrições sem pontuação continuam na lista.
		$query->set(
			'meta_query',
			array(
				'relation'         => 'OR',
				'cedu_insc_pontos' => array(
					'key'  => $meta,
					'type' => 'NUMERIC',
				),
				array(
					'key'     => $meta,
					'compare' => 'NOT EXISTS',
				),
			)
		);
		$query->set( 'orderby', 'cedu_insc_pontos' );
	}
}
