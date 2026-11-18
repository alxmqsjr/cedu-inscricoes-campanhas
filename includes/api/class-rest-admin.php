<?php
/**
 * Rotas internas do gerenciador (exclusão de inscrições em lotes).
 *
 * Só para administradores (manage_options), com o nonce "wp_rest" da sessão.
 * Não há rota de exclusão de mídias avulsas: a mídia só sai junto com a
 * inscrição a que pertence.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Rotas de administração.
 */
final class Rest_Admin {

	public const NAMESPACE = 'cedu-inscricoes/v1';

	/** Máximo de inscrições apagadas por requisição. */
	public const LOTE_MAXIMO = 20;

	/** Registra as rotas. */
	public static function registrar(): void {
		add_action( 'rest_api_init', array( self::class, 'registrar_rotas' ) );
	}

	/** Registra as rotas. */
	public static function registrar_rotas(): void {
		register_rest_route(
			self::NAMESPACE,
			'/excluir',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'excluir_ids' ),
				'permission_callback' => array( self::class, 'pode' ),
				'args'                => array(
					'ids' => array(
						'type'     => 'array',
						'items'    => array( 'type' => 'integer' ),
						'required' => true,
						'maxItems' => self::LOTE_MAXIMO,
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/excluir-filtro',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( self::class, 'excluir_filtro' ),
				'permission_callback' => array( self::class, 'pode' ),
				'args'                => array(
					'edicao' => array(
						'type'    => 'string',
						'default' => '',
					),
					'status' => array(
						'type'    => 'string',
						'default' => '',
					),
					'busca'  => array(
						'type'      => 'string',
						'default'   => '',
						'maxLength' => 200,
					),
					'lote'   => array(
						'type'    => 'integer',
						'default' => 5,
						'minimum' => 1,
						'maximum' => self::LOTE_MAXIMO,
					),
				),
			)
		);
	}

	/** Só administradores. */
	public static function pode(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Apaga as inscrições indicadas.
	 *
	 * @param WP_REST_Request $request Requisição.
	 */
	public static function excluir_ids( WP_REST_Request $request ): WP_REST_Response {
		$ids = array_unique( array_filter( array_map( 'absint', (array) $request['ids'] ) ) );

		return new WP_REST_Response( self::apagar_varias( $ids ) + array( 'processados' => count( $ids ) ) );
	}

	/**
	 * Apaga o próximo lote das inscrições que atendem ao filtro.
	 * Sempre do início: as já apagadas somem da consulta.
	 *
	 * @param WP_REST_Request $request Requisição.
	 */
	public static function excluir_filtro( WP_REST_Request $request ): WP_REST_Response {
		$filtros = array(
			'edicao' => (string) $request['edicao'],
			'status' => (string) $request['status'],
			'busca'  => (string) $request['busca'],
		);

		$ids       = Repositorio::ids( $filtros, (int) $request['lote'], 0, 'ID', 'ASC' );
		$resultado = self::apagar_varias( $ids );
		$restantes = Repositorio::contar( $filtros );

		// Se nada do lote pôde ser apagado, para (evita repetir o mesmo lote para sempre).
		$travou = ! empty( $ids ) && 0 === $resultado['apagadas'];

		return new WP_REST_Response(
			$resultado + array(
				'processados' => count( $ids ),
				'restantes'   => $restantes,
				'fim'         => 0 === $restantes || empty( $ids ) || $travou,
				'erro'        => $travou ? __( 'Nenhuma inscrição deste lote pôde ser apagada.', 'cedu-inscricoes-campanhas' ) : '',
			)
		);
	}

	/**
	 * Apaga uma lista de inscrições.
	 *
	 * @param int[] $ids IDs.
	 * @return array{apagadas: int, midias: int, log: string[]}
	 */
	private static function apagar_varias( array $ids ): array {
		$apagadas = 0;
		$midias   = 0;
		$log      = array();

		foreach ( $ids as $id ) {
			if ( get_post_type( $id ) !== Esquema::POST_TYPE ) {
				/* translators: %d: ID. */
				$log[] = sprintf( __( '#%d: ignorado (não é uma inscrição).', 'cedu-inscricoes-campanhas' ), $id );
				continue;
			}

			$r = Repositorio::apagar( $id );
			if ( $r['ok'] ) {
				++$apagadas;
				$midias += $r['midias'];
				/* translators: 1: ID, 2: título, 3: quantidade de mídias. */
				$log[] = sprintf( __( '#%1$d "%2$s": apagada (%3$d mídia(s)).', 'cedu-inscricoes-campanhas' ), $id, $r['titulo'], $r['midias'] );
			} else {
				/* translators: %d: ID. */
				$log[] = sprintf( __( '#%d: falha ao apagar.', 'cedu-inscricoes-campanhas' ), $id );
			}
		}

		return array(
			'apagadas' => $apagadas,
			'midias'   => $midias,
			'log'      => $log,
		);
	}
}
