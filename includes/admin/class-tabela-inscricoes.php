<?php
/**
 * Tabela da tela "Gerenciar": inscrições com filtros, busca e exclusão em lotes.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Listagem das inscrições.
 */
final class Tabela_Inscricoes extends \WP_List_Table {

	/**
	 * Filtros da requisição atual.
	 *
	 * @var array<string, string>
	 */
	private array $filtros;

	/** Construtor. */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'inscricao',
				'plural'   => 'inscricoes',
				'ajax'     => false,
			)
		);

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- filtros de leitura.
		$this->filtros = array(
			'edicao'     => sanitize_title( wp_unslash( $_GET['edicao'] ?? '' ) ),
			'status'     => sanitize_title( wp_unslash( $_GET['status_inscricao'] ?? '' ) ),
			'busca'      => sanitize_text_field( wp_unslash( $_GET['s'] ?? '' ) ),
			'duplicadas' => empty( $_GET['duplicadas'] ) ? '' : '1',
		);
		// phpcs:enable
	}

	/**
	 * Filtros em uso (para o JS do "apagar todas").
	 *
	 * @return array<string, string>
	 */
	public function filtros(): array {
		return $this->filtros;
	}

	/** Carrega a página atual. */
	public function prepare_items(): void {
		$por_pagina = $this->get_items_per_page( Admin::OPCAO_POR_PAGINA, 50 );
		$pagina     = $this->get_pagenum();
		$total      = Repositorio::contar( $this->filtros );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- ordenação de leitura.
		$ordem   = sanitize_key( wp_unslash( $_GET['orderby'] ?? 'ID' ) );
		$direcao = 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ?? 'desc' ) ) ) ? 'ASC' : 'DESC';
		// phpcs:enable
		$ordem = in_array( $ordem, array( 'post_title', 'post_date', 'pontuacao' ), true ) ? $ordem : 'ID';

		$ids = Repositorio::ids( $this->filtros, $por_pagina, ( $pagina - 1 ) * $por_pagina, $ordem, $direcao );

		// Carrega posts, metas e termos de uma vez (em vez de uma consulta por linha).
		_prime_post_caches( $ids, true, true );
		$this->carregar_anexos( $ids );

		$this->items = array_filter( array_map( 'get_post', $ids ) );

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'titulo' );
		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $por_pagina,
				'total_pages' => (int) ceil( $total / $por_pagina ),
			)
		);
	}

	/**
	 * Carrega de uma vez os anexos (capa e imagens públicas) das linhas.
	 *
	 * @param int[] $ids IDs das inscrições (metas já em cache).
	 */
	private function carregar_anexos( array $ids ): void {
		$anexos = array();
		foreach ( $ids as $id ) {
			$anexos[] = (int) get_post_meta( $id, '_thumbnail_id', true );
			foreach ( Esquema::campos_imagem_publica() as $campo ) {
				$img = json_decode( (string) get_post_meta( $id, $campo, true ), true );
				if ( is_array( $img ) && ! empty( $img['attachment_id'] ) ) {
					$anexos[] = (int) $img['attachment_id'];
				}
			}
		}
		_prime_post_caches( array_filter( array_unique( $anexos ) ), false, true );
	}

	/**
	 * Colunas.
	 *
	 * @return array<string, string>
	 */
	public function get_columns(): array {
		return array(
			'cb'        => '<input type="checkbox" />',
			'titulo'    => __( 'Título', 'cedu-inscricoes-campanhas' ),
			'pessoa'    => __( 'Nome / E-mail', 'cedu-inscricoes-campanhas' ),
			'local'     => __( 'Local', 'cedu-inscricoes-campanhas' ),
			'termos'    => __( 'Edição / Status', 'cedu-inscricoes-campanhas' ),
			'imagens'   => __( 'Capa e imagens públicas', 'cedu-inscricoes-campanhas' ),
			'pontuacao' => __( 'Pontuação', 'cedu-inscricoes-campanhas' ),
			'data'      => __( 'Data', 'cedu-inscricoes-campanhas' ),
		);
	}

	/**
	 * Colunas ordenáveis.
	 *
	 * @return array<string, array{0: string, 1: bool}>
	 */
	protected function get_sortable_columns(): array {
		return array(
			'titulo'    => array( 'post_title', false ),
			'pontuacao' => array( 'pontuacao', true ),
			'data'      => array( 'post_date', true ),
		);
	}

	/**
	 * Ações em massa (executadas pelo gerenciador.js em lotes).
	 *
	 * @return array<string, string>
	 */
	protected function get_bulk_actions(): array {
		return array(
			'icamp-excluir' => __( 'Apagar de vez (com a mídia)', 'cedu-inscricoes-campanhas' ),
		);
	}

	/**
	 * Visões: todas e possíveis duplicadas.
	 *
	 * @return array<string, string>
	 */
	protected function get_views(): array {
		$base = Admin::url( Admin::PAGINA_GERENCIAR );
		$dup  = Repositorio::contar( array( 'duplicadas' => '1' ) );

		return array(
			'todas'      => sprintf(
				'<a href="%s"%s>%s <span class="count">(%d)</span></a>',
				esc_url( $base ),
				'' === $this->filtros['duplicadas'] ? ' class="current" aria-current="page"' : '',
				esc_html__( 'Todas', 'cedu-inscricoes-campanhas' ),
				Repositorio::contar()
			),
			'duplicadas' => sprintf(
				'<a href="%s"%s title="%s">%s <span class="count">(%d)</span></a>',
				esc_url( add_query_arg( 'duplicadas', '1', $base ) ),
				'' !== $this->filtros['duplicadas'] ? ' class="current" aria-current="page"' : '',
				esc_attr__( 'Mesmo título e mesmo e-mail', 'cedu-inscricoes-campanhas' ),
				esc_html__( 'Possíveis duplicadas', 'cedu-inscricoes-campanhas' ),
				$dup
			),
		);
	}

	/**
	 * Filtros por edição e status, e o botão de apagar a lista filtrada.
	 *
	 * @param string $onde 'top' ou 'bottom'.
	 */
	protected function extra_tablenav( $onde ): void {
		if ( 'top' !== $onde ) {
			return;
		}

		echo '<div class="alignleft actions">';
		$this->select_termos( Esquema::TAX_EDICAO, 'edicao', $this->filtros['edicao'], __( 'Todas as edições', 'cedu-inscricoes-campanhas' ) );
		$this->select_termos( Esquema::TAX_STATUS, 'status_inscricao', $this->filtros['status'], __( 'Todos os status', 'cedu-inscricoes-campanhas' ) );
		submit_button( __( 'Filtrar', 'cedu-inscricoes-campanhas' ), '', 'filtrar', false );
		echo '</div>';

		if ( '' === $this->filtros['duplicadas'] && $this->_pagination_args['total_items'] > 0 ) {
			printf(
				'<div class="alignleft actions"><button type="button" class="button icamp-perigo" id="icamp-apagar-todas">%s</button></div>',
				esc_html(
					sprintf(
						/* translators: %d: quantidade. */
						__( 'Apagar todas desta lista (%d)', 'cedu-inscricoes-campanhas' ),
						$this->_pagination_args['total_items']
					)
				)
			);
		}
	}

	/**
	 * Select de termos de uma taxonomia.
	 *
	 * @param string $taxonomia Taxonomia.
	 * @param string $nome      Nome do parâmetro.
	 * @param string $atual     Slug selecionado.
	 * @param string $todos     Rótulo da opção vazia.
	 */
	private function select_termos( string $taxonomia, string $nome, string $atual, string $todos ): void {
		if ( ! taxonomy_exists( $taxonomia ) ) {
			return;
		}
		$termos = get_terms(
			array(
				'taxonomy'   => $taxonomia,
				'hide_empty' => false,
			)
		);
		if ( is_wp_error( $termos ) ) {
			return;
		}

		printf( '<label class="screen-reader-text" for="icamp-%1$s">%2$s</label><select name="%1$s" id="icamp-%1$s">', esc_attr( $nome ), esc_html( $todos ) );
		printf( '<option value="">%s</option>', esc_html( $todos ) );
		foreach ( $termos as $termo ) {
			printf(
				'<option value="%s"%s>%s (%d)</option>',
				esc_attr( $termo->slug ),
				selected( $atual, $termo->slug, false ),
				esc_html( $termo->name ),
				(int) $termo->count
			);
		}
		echo '</select>';
	}

	/** Mensagem da tabela vazia. */
	public function no_items(): void {
		esc_html_e( 'Nenhuma inscrição encontrada.', 'cedu-inscricoes-campanhas' );
	}

	/**
	 * Checkbox da linha.
	 *
	 * @param \WP_Post $post Inscrição.
	 */
	protected function column_cb( $post ): string {
		return sprintf(
			'<label class="screen-reader-text" for="icamp-cb-%1$d">%2$s</label><input type="checkbox" name="ids[]" id="icamp-cb-%1$d" value="%1$d" />',
			$post->ID,
			esc_html( get_the_title( $post ) )
		);
	}

	/**
	 * Título, ID externo e ações da linha.
	 *
	 * @param \WP_Post $post Inscrição.
	 */
	protected function column_titulo( \WP_Post $post ): string {
		$id_externo = Esquema::valor( $post->ID, 'id' );

		$acoes = array(
			'edit'   => sprintf( '<a href="%s">%s</a>', esc_url( (string) get_edit_post_link( $post->ID ) ), esc_html__( 'Editar', 'cedu-inscricoes-campanhas' ) ),
			'view'   => sprintf( '<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url( (string) get_permalink( $post ) ), esc_html__( 'Ver', 'cedu-inscricoes-campanhas' ) ),
			'delete' => sprintf( '<a href="#" class="icamp-apagar-uma" data-id="%d">%s</a>', $post->ID, esc_html__( 'Apagar de vez', 'cedu-inscricoes-campanhas' ) ),
		);

		return sprintf(
			'<strong><a class="row-title" href="%s">%s</a></strong><span class="icamp-detalhe">#%d · %s · %s</span>%s',
			esc_url( (string) get_edit_post_link( $post->ID ) ),
			esc_html( get_the_title( $post ) ),
			$post->ID,
			esc_html( get_post_status_object( $post->post_status )->label ?? $post->post_status ),
			'' !== $id_externo
				/* translators: %s: identificador do formulário. */
				? esc_html( sprintf( __( 'formulário: %s', 'cedu-inscricoes-campanhas' ), $id_externo ) )
				: '<em>' . esc_html__( 'sem ID do formulário', 'cedu-inscricoes-campanhas' ) . '</em>',
			$this->row_actions( $acoes )
		);
	}

	/**
	 * Nome e e-mail.
	 *
	 * @param \WP_Post $post Inscrição.
	 */
	protected function column_pessoa( \WP_Post $post ): string {
		$nome  = Esquema::valor( $post->ID, 'nome' );
		$email = Esquema::valor( $post->ID, 'email' );
		return esc_html( '' !== $nome ? $nome : '—' ) . ( '' !== $email ? '<span class="icamp-detalhe">' . esc_html( $email ) . '</span>' : '' );
	}

	/**
	 * Município / UF.
	 *
	 * @param \WP_Post $post Inscrição.
	 */
	protected function column_local( \WP_Post $post ): string {
		$local = implode( ' / ', array_filter( array( Esquema::valor( $post->ID, 'municipio' ), Esquema::valor( $post->ID, 'estado' ) ) ) );
		return esc_html( '' !== $local ? $local : '—' );
	}

	/**
	 * Edição e status.
	 *
	 * @param \WP_Post $post Inscrição.
	 */
	protected function column_termos( \WP_Post $post ): string {
		$partes = array();
		foreach ( array( Esquema::TAX_EDICAO, Esquema::TAX_STATUS ) as $taxonomia ) {
			$termos = get_the_terms( $post, $taxonomia );
			if ( is_array( $termos ) ) {
				$partes[] = esc_html( implode( ', ', wp_list_pluck( $termos, 'name' ) ) );
			}
		}
		return $partes ? implode( '<br>', $partes ) : '—';
	}

	/**
	 * Miniaturas da capa e das imagens públicas.
	 *
	 * @param \WP_Post $post Inscrição.
	 */
	protected function column_imagens( \WP_Post $post ): string {
		$html  = '';
		$capa  = (int) get_post_thumbnail_id( $post );
		$url   = $capa ? wp_get_attachment_image_url( $capa, 'thumbnail' ) : '';
		$html .= $url
			? sprintf( '<img class="icamp-capa" src="%s" alt="" title="%s" loading="lazy">', esc_url( $url ), esc_attr( Midia::eh_imagem_padrao( $capa ) ? __( 'Capa (imagem padrão)', 'cedu-inscricoes-campanhas' ) : __( 'Capa', 'cedu-inscricoes-campanhas' ) ) )
			: '<span class="icamp-sem-capa" title="' . esc_attr__( 'Sem capa', 'cedu-inscricoes-campanhas' ) . '">—</span>';

		for ( $n = 1; $n <= Esquema::total_imagens_publicas(); $n++ ) {
			$img = json_decode( (string) get_post_meta( $post->ID, Esquema::meta_imagem_publica( $n ), true ), true );
			if ( ! is_array( $img ) || empty( $img['local'] ) || empty( $img['attachment_id'] ) ) {
				continue;
			}
			$url = wp_get_attachment_image_url( (int) $img['attachment_id'], 'thumbnail' );
			if ( $url ) {
				$html .= sprintf( '<img class="icamp-mini" src="%s" alt="" title="%s" loading="lazy">', esc_url( $url ), esc_attr( Esquema::meta_imagem_publica( $n ) ) );
			}
		}

		return '<div class="icamp-imagens">' . $html . '</div>';
	}

	/**
	 * Pontuação.
	 *
	 * @param \WP_Post $post Inscrição.
	 */
	protected function column_pontuacao( \WP_Post $post ): string {
		$valor = Esquema::valor( $post->ID, 'pontuacao' );
		return '' !== $valor ? esc_html( $valor ) : '—';
	}

	/**
	 * Data de publicação.
	 *
	 * @param \WP_Post $post Inscrição.
	 */
	protected function column_data( \WP_Post $post ): string {
		return esc_html( get_the_date( 'd/m/Y H:i', $post ) );
	}
}
