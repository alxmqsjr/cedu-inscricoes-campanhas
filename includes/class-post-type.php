<?php
/**
 * Registro do CPT "inscricoes" e das taxonomias, como reserva.
 *
 * Nesta instalação o tema registra o CPT (library/cpt-inscricoes.php) e
 * continua sendo o dono dele: as inscrições precisam aparecer mesmo com o
 * plugin desativado. Este registro só acontece quando ninguém registrou
 * antes (outra instalação, outro tema), com os mesmos argumentos do tema.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

/**
 * Registro de reserva do CPT e das taxonomias.
 */
final class Post_Type {

	/** Registra os hooks. */
	public static function registrar(): void {
		// Prioridade 20: o tema registra na prioridade 10 do mesmo hook.
		add_action( 'init', array( self::class, 'registrar_se_ausente' ), 20 );
	}

	/** Registra o que ainda não existir. */
	public static function registrar_se_ausente(): void {
		if ( ! post_type_exists( Esquema::POST_TYPE ) ) {
			register_post_type( Esquema::POST_TYPE, self::args_post_type() );
		}

		if ( ! taxonomy_exists( Esquema::TAX_STATUS ) ) {
			register_taxonomy(
				Esquema::TAX_STATUS,
				Esquema::POST_TYPE,
				array(
					'labels'            => array(
						'name'          => __( 'Status', 'cedu-inscricoes-campanhas' ),
						'singular_name' => __( 'Status', 'cedu-inscricoes-campanhas' ),
						'all_items'     => __( 'Todos os Status', 'cedu-inscricoes-campanhas' ),
						'edit_item'     => __( 'Editar Status', 'cedu-inscricoes-campanhas' ),
						'add_new_item'  => __( 'Adicionar Novo Status', 'cedu-inscricoes-campanhas' ),
					),
					'hierarchical'      => true,
					'show_ui'           => true,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'rewrite'           => array( 'slug' => 'status' ),
				)
			);
		}

		if ( ! taxonomy_exists( Esquema::TAX_EDICAO ) ) {
			register_taxonomy(
				Esquema::TAX_EDICAO,
				Esquema::POST_TYPE,
				array(
					'labels'            => array(
						'name'          => __( 'Edição', 'cedu-inscricoes-campanhas' ),
						'singular_name' => __( 'Edição', 'cedu-inscricoes-campanhas' ),
						'all_items'     => __( 'Todas as Edições', 'cedu-inscricoes-campanhas' ),
						'edit_item'     => __( 'Editar Edição', 'cedu-inscricoes-campanhas' ),
						'add_new_item'  => __( 'Adicionar Nova Edição', 'cedu-inscricoes-campanhas' ),
					),
					'hierarchical'      => true,
					'show_ui'           => true,
					'show_admin_column' => true,
					'show_in_rest'      => true,
					'rewrite'           => array( 'slug' => 'edicao' ),
				)
			);
		}
	}

	/**
	 * Argumentos do CPT (idênticos aos do tema).
	 *
	 * @return array<string, mixed>
	 */
	private static function args_post_type(): array {
		return array(
			'labels'             => array(
				'name'          => __( 'Inscrições', 'cedu-inscricoes-campanhas' ),
				'singular_name' => __( 'Inscrição', 'cedu-inscricoes-campanhas' ),
				'menu_name'     => __( 'Inscrições', 'cedu-inscricoes-campanhas' ),
				'add_new'       => __( 'Nova Inscrição', 'cedu-inscricoes-campanhas' ),
				'add_new_item'  => __( 'Adicionar Nova Inscrição', 'cedu-inscricoes-campanhas' ),
				'edit_item'     => __( 'Editar Inscrição', 'cedu-inscricoes-campanhas' ),
				'view_item'     => __( 'Ver Inscrição', 'cedu-inscricoes-campanhas' ),
				'all_items'     => __( 'Todas as Inscrições', 'cedu-inscricoes-campanhas' ),
				'search_items'  => __( 'Buscar Inscrições', 'cedu-inscricoes-campanhas' ),
				'not_found'     => __( 'Nenhuma inscrição encontrada.', 'cedu-inscricoes-campanhas' ),
			),
			'public'             => true,
			'publicly_queryable' => true,
			'show_ui'            => true,
			'show_in_menu'       => true,
			'query_var'          => true,
			'rewrite'            => array(
				'slug'       => 'inscricoes',
				'with_front' => false,
			),
			'has_archive'        => true,
			'hierarchical'       => false,
			'menu_position'      => 5,
			'menu_icon'          => 'dashicons-clipboard',
			'supports'           => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'show_in_rest'       => true,
		);
	}
}
