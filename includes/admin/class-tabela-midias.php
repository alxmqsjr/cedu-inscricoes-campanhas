<?php
/**
 * Tabela da tela "Mídias": consulta, somente leitura.
 *
 * Não há exclusão de mídias aqui (nem em massa nem individual): a mídia de
 * uma inscrição só é apagada junto com a própria inscrição. Para remover um
 * arquivo avulso, use a Biblioteca de Mídia do WordPress.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Listagem das mídias por origem.
 */
final class Tabela_Midias extends \WP_List_Table {

	/**
	 * Origem selecionada.
	 *
	 * @var string
	 */
	private string $origem;

	/** Construtor. */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'midia',
				'plural'   => 'midias',
				'ajax'     => false,
			)
		);

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filtro de leitura.
		$origem       = sanitize_key( wp_unslash( $_GET['origem'] ?? 'inscricoes' ) );
		$this->origem = in_array( $origem, Repositorio::ORIGENS_MIDIA, true ) ? $origem : 'inscricoes';
	}

	/**
	 * Rótulos e descrições das origens.
	 *
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function origens(): array {
		return array(
			'inscricoes'        => array(
				__( 'Das inscrições', 'cedu-inscricoes-campanhas' ),
				__( 'Arquivos anexados a uma inscrição (capa e imagens públicas baixadas pela API).', 'cedu-inscricoes-campanhas' ),
			),
			'nao_referenciadas' => array(
				__( 'Não referenciadas', 'cedu-inscricoes-campanhas' ),
				__( 'Anexadas a uma inscrição, mas que não são a capa nem uma imagem pública dela (sobras de reenvios feitos pelo plugin antigo, ou uploads manuais).', 'cedu-inscricoes-campanhas' ),
			),
			'sem_autor'         => array(
				__( 'Sem autor', 'cedu-inscricoes-campanhas' ),
				__( 'Qualquer arquivo do site cujo "Enviado por" não é um usuário válido. Inclui mídias de outros conteúdos (ações, atividades, midiateca…).', 'cedu-inscricoes-campanhas' ),
			),
			'pai_apagado'       => array(
				__( 'Post pai apagado', 'cedu-inscricoes-campanhas' ),
				__( 'Arquivos anexados a um post que não existe mais.', 'cedu-inscricoes-campanhas' ),
			),
		);
	}

	/** Origem selecionada. */
	public function origem(): string {
		return $this->origem;
	}

	/** Carrega a página atual. */
	public function prepare_items(): void {
		$por_pagina = $this->get_items_per_page( Admin::OPCAO_POR_PAGINA, 50 );
		$pagina     = $this->get_pagenum();
		$total      = Repositorio::contar_midias( $this->origem );

		$this->items = Repositorio::midias( $this->origem, $por_pagina, ( $pagina - 1 ) * $por_pagina );

		// Anexos e posts pais (com metas) em poucas consultas.
		_prime_post_caches( array_map( static fn( $m ) => (int) $m->ID, $this->items ), false, true );
		_prime_post_caches( array_filter( array_unique( array_map( static fn( $m ) => (int) $m->post_parent, $this->items ) ) ), false, true );

		$this->_column_headers = array( $this->get_columns(), array(), array(), 'arquivo' );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $por_pagina,
				'total_pages' => (int) ceil( $total / $por_pagina ),
			)
		);
	}

	/**
	 * Colunas (sem checkbox: não há ações em massa).
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'arquivo' => __( 'Arquivo', 'cedu-inscricoes-campanhas' ),
			'data'    => __( 'Enviado em', 'cedu-inscricoes-campanhas' ),
			'tamanho' => __( 'Tamanho', 'cedu-inscricoes-campanhas' ),
			'autor'   => __( 'Enviado por', 'cedu-inscricoes-campanhas' ),
			'origem'  => __( 'Origem', 'cedu-inscricoes-campanhas' ),
		);
	}

	/**
	 * Visões por origem, com contagem.
	 *
	 * @return array<string, string>
	 */
	protected function get_views(): array {
		$visoes = array();
		foreach ( self::origens() as $chave => $rotulo ) {
			$visoes[ $chave ] = sprintf(
				'<a href="%s"%s title="%s">%s <span class="count">(%d)</span></a>',
				esc_url( Admin::url( Admin::PAGINA_MIDIAS, array( 'origem' => $chave ) ) ),
				$chave === $this->origem ? ' class="current" aria-current="page"' : '',
				esc_attr( $rotulo[1] ),
				esc_html( $rotulo[0] ),
				Repositorio::contar_midias( $chave )
			);
		}
		return $visoes;
	}

	/** Mensagem da tabela vazia. */
	public function no_items(): void {
		esc_html_e( 'Nenhuma mídia nesta origem.', 'cedu-inscricoes-campanhas' );
	}

	/**
	 * Miniatura (ou ícone) e nome do arquivo.
	 *
	 * @param object $m Linha.
	 */
	protected function column_arquivo( $m ): string {
		$id      = (int) $m->ID;
		$arquivo = (string) get_attached_file( $id );
		$nome    = '' !== $arquivo ? wp_basename( $arquivo ) : get_the_title( $id );
		$imagem  = str_starts_with( (string) $m->post_mime_type, 'image/' );
		$preview = $imagem ? wp_get_attachment_image_url( $id, 'thumbnail' ) : '';
		$classe  = '';
		if ( ! $preview ) {
			$preview = wp_mime_type_icon( $id );
			$classe  = ' class="icamp-icone"';
		}

		return sprintf(
			'<div class="icamp-arquivo"><img src="%s" alt=""%s loading="lazy" title="%s"><span><a href="%s">%s</a><span class="icamp-detalhe">#%d · %s</span></span></div>',
			esc_url( (string) $preview ),
			$classe,
			esc_attr( (string) $m->post_mime_type ),
			esc_url( (string) get_edit_post_link( $id ) ),
			esc_html( $nome ),
			$id,
			esc_html( (string) $m->post_mime_type )
		);
	}

	/**
	 * Data do envio.
	 *
	 * @param object $m Linha.
	 */
	protected function column_data( $m ): string {
		return esc_html( mysql2date( 'd/m/Y H:i', (string) $m->post_date ) );
	}

	/**
	 * Tamanho (da metadata; se faltar, do disco).
	 *
	 * @param object $m Linha.
	 */
	protected function column_tamanho( $m ): string {
		$meta    = wp_get_attachment_metadata( (int) $m->ID );
		$tamanho = is_array( $meta ) && ! empty( $meta['filesize'] ) ? (int) $meta['filesize'] : 0;
		if ( ! $tamanho ) {
			$arquivo = (string) get_attached_file( (int) $m->ID );
			$tamanho = '' !== $arquivo && is_file( $arquivo ) ? (int) filesize( $arquivo ) : 0;
		}
		return $tamanho ? esc_html( (string) size_format( $tamanho ) ) : '—';
	}

	/**
	 * Autor.
	 *
	 * @param object $m Linha.
	 */
	protected function column_autor( $m ): string {
		$usuario = (int) $m->post_author > 0 ? get_userdata( (int) $m->post_author ) : false;
		return $usuario
			? esc_html( $usuario->display_name )
			: '<span class="icamp-sem-autor">' . esc_html__( '(sem autor)', 'cedu-inscricoes-campanhas' ) . '</span>';
	}

	/**
	 * Origem (inscrição e campo correspondente).
	 *
	 * @param object $m Linha.
	 */
	protected function column_origem( $m ): string {
		$origem = Repositorio::origem_da_midia( (int) $m->ID, (int) $m->post_parent );
		return '' !== $origem['link']
			? sprintf( '<a href="%s">%s</a>', esc_url( $origem['link'] ), esc_html( $origem['texto'] ) )
			: esc_html( $origem['texto'] );
	}
}
