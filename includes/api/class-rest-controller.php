<?php
/**
 * Endpoint público que recebe as inscrições do formulário externo.
 *
 * POST /wp-json/cedu/v1/inscricao
 * Cabeçalho: X-CEDU-API-KEY: <chave>
 * Respostas: 201 criada · 200 atualizada · 400 sem dados · 401 chave inválida
 *            403 recebimento fechado · 409 mesmo envio em processamento
 *            503 chave não configurada
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

defined( 'ABSPATH' ) || exit;

/**
 * Controlador REST do recebimento de inscrições.
 */
final class Rest_Controller extends WP_REST_Controller {

	/** Construtor. */
	public function __construct() {
		$this->namespace = 'cedu/v1';
		$this->rest_base = 'inscricao';
	}

	/** Registra o controlador. */
	public static function registrar(): void {
		add_action(
			'rest_api_init',
			static function () {
				( new self() )->register_routes();
			}
		);
	}

	/** Registra a rota. */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_item' ),
					'permission_callback' => array( $this, 'create_item_permissions_check' ),
				),
				'schema' => array( $this, 'get_public_item_schema' ),
			)
		);
	}

	/**
	 * Confere a chave da API.
	 *
	 * @param WP_REST_Request $request Requisição.
	 * @return true|WP_Error
	 */
	public function create_item_permissions_check( $request ) {
		if ( ! Autenticacao::configurada() ) {
			return new WP_Error( 'api_nao_configurada', __( 'API de inscrições sem chave configurada.', 'cedu-inscricoes-campanhas' ), array( 'status' => 503 ) );
		}

		if ( ! Autenticacao::valida( (string) $request->get_header( Autenticacao::CABECALHO ) ) ) {
			return new WP_Error( 'unauthorized', __( 'Chave de API inválida.', 'cedu-inscricoes-campanhas' ), array( 'status' => 401 ) );
		}

		// Só depois da chave: quem não a tem não descobre se o recebimento está aberto.
		if ( ! Config::recebimento_aberto() ) {
			return new WP_Error( 'recebimento_fechado', Config::mensagem_fechado(), array( 'status' => 403 ) );
		}

		return true;
	}

	/**
	 * Cria ou atualiza a inscrição.
	 *
	 * @param WP_REST_Request $request Requisição.
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_item( $request ) {
		$dados = $request->get_json_params();

		// Precisa ser um objeto JSON; uma lista ([…]) criaria uma inscrição vazia.
		if ( ! is_array( $dados ) || empty( $dados ) || array_keys( $dados ) === range( 0, count( $dados ) - 1 ) ) {
			return new WP_Error( 'dados_vazios', __( 'Nenhum dado recebido.', 'cedu-inscricoes-campanhas' ), array( 'status' => 400 ) );
		}

		$resultado = Importador::importar( $dados );
		if ( is_wp_error( $resultado ) ) {
			return $resultado;
		}

		return new WP_REST_Response(
			array(
				'message' => $resultado['criada']
					? __( 'Inscrição criada com sucesso.', 'cedu-inscricoes-campanhas' )
					: __( 'Inscrição atualizada com sucesso.', 'cedu-inscricoes-campanhas' ),
				'post_id' => $resultado['post_id'],
			),
			$resultado['criada'] ? 201 : 200
		);
	}

	/**
	 * Esquema da resposta.
	 *
	 * @return array<string, mixed>
	 */
	public function get_item_schema() {
		if ( $this->schema ) {
			return $this->add_additional_fields_schema( $this->schema );
		}

		$this->schema = array(
			'$schema'    => 'http://json-schema.org/draft-04/schema#',
			'title'      => 'inscricao',
			'type'       => 'object',
			'properties' => array(
				'message' => array(
					'description' => __( 'Mensagem do resultado.', 'cedu-inscricoes-campanhas' ),
					'type'        => 'string',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
				'post_id' => array(
					'description' => __( 'ID da inscrição criada ou atualizada.', 'cedu-inscricoes-campanhas' ),
					'type'        => 'integer',
					'context'     => array( 'view' ),
					'readonly'    => true,
				),
			),
		);

		return $this->add_additional_fields_schema( $this->schema );
	}
}
