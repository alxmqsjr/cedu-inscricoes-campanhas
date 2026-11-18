<?php
/**
 * Consultas e exclusão de inscrições; consulta (somente leitura) de mídias.
 *
 * As consultas usam SQL direto e preparado para não sofrer efeito dos
 * filtros de pre_get_posts do tema e de outros plugins.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

/**
 * Acesso aos dados.
 */
final class Repositorio {

	/** Status ignorados em todas as consultas. */
	private const STATUS_IGNORADOS = array( 'trash', 'auto-draft', 'inherit' );

	/** Origens possíveis no filtro de mídias. */
	public const ORIGENS_MIDIA = array( 'inscricoes', 'nao_referenciadas', 'sem_autor', 'pai_apagado' );

	/**
	 * Inscrição com o identificador do formulário externo.
	 *
	 * @param string $id_externo Valor do campo "id" do JSON.
	 * @return int ID do post, ou 0.
	 */
	public static function buscar_por_id_externo( string $id_externo ): int {
		global $wpdb;

		if ( '' === $id_externo || '' === Esquema::meta_id() ) {
			return 0;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT p.ID FROM {$wpdb->posts} p
				 INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = %s
				 WHERE p.post_type = %s AND p.post_status NOT IN ('trash','auto-draft') AND m.meta_value = %s
				 ORDER BY p.ID ASC LIMIT 1",
				Esquema::meta_id(),
				Esquema::POST_TYPE,
				$id_externo
			)
		);
	}

	/**
	 * Monta FROM/WHERE da listagem de inscrições conforme os filtros.
	 *
	 * @param array<string, string> $filtros Chaves: edicao, status, busca, duplicadas ('1').
	 * @return string Trecho SQL já preparado, começando em "FROM".
	 */
	private static function sql_filtros( array $filtros ): string {
		global $wpdb;

		$sql = $wpdb->prepare(
			"FROM {$wpdb->posts} p WHERE p.post_type = %s AND p.post_status NOT IN ('trash','auto-draft','inherit')",
			Esquema::POST_TYPE
		);

		foreach ( array(
			'edicao' => Esquema::TAX_EDICAO,
			'status' => Esquema::TAX_STATUS,
		) as $chave => $taxonomia ) {
			$slug = sanitize_title( $filtros[ $chave ] ?? '' );
			if ( '' === $slug ) {
				continue;
			}
			$sql .= $wpdb->prepare(
				" AND EXISTS ( SELECT 1 FROM {$wpdb->term_relationships} tr
				   INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
				   INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
				   WHERE tr.object_id = p.ID AND tt.taxonomy = %s AND t.slug = %s )",
				$taxonomia,
				$slug
			);
		}

		$busca = trim( (string) ( $filtros['busca'] ?? '' ) );
		if ( '' !== $busca ) {
			$like  = '%' . $wpdb->esc_like( $busca ) . '%';
			$metas = array_values(
				array_filter(
					array(
						Esquema::campo( 'email' ),
						Esquema::campo( 'nome' ),
						Esquema::campo( 'instituicao' ),
						Esquema::campo( 'municipio' ),
						Esquema::meta_id(),
					)
				)
			);
			$metas = $metas ? $metas : array( '' );
			$lugar = implode( ',', array_fill( 0, count( $metas ), '%s' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $lugar só tem marcadores %s.
			$sql .= $wpdb->prepare(
				" AND ( p.post_title LIKE %s OR CAST(p.ID AS CHAR) = %s OR EXISTS (
				   SELECT 1 FROM {$wpdb->postmeta} bm WHERE bm.post_id = p.ID
				   AND bm.meta_key IN ({$lugar})
				   AND bm.meta_value LIKE %s ) )",
				array_merge( array( $like, $busca ), $metas, array( $like ) )
			);
			// phpcs:enable
		}

		if ( ! empty( $filtros['duplicadas'] ) ) {
			// Mesmo título e mesmo e-mail (sem diferenciar maiúsculas/espaços),
			// agrupando uma vez em vez de comparar cada par de inscrições.
			$chaves = "SELECT x.ID, LOWER(TRIM(x.post_title)) AS t, LOWER(TRIM(COALESCE(em.meta_value, ''))) AS e
				FROM {$wpdb->posts} x
				LEFT JOIN {$wpdb->postmeta} em ON em.post_id = x.ID AND em.meta_key = %s
				WHERE x.post_type = %s AND x.post_status NOT IN ('trash','auto-draft','inherit')";
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- $chaves só tem nomes de tabela e dois %s, preenchidos aqui.
			$sql .= $wpdb->prepare(
				" AND p.ID IN ( SELECT d.ID FROM ( {$chaves} ) d
				   INNER JOIN ( SELECT k.t, k.e FROM ( {$chaves} ) k GROUP BY k.t, k.e HAVING COUNT(DISTINCT k.ID) > 1 ) g
				   ON g.t = d.t AND g.e = d.e )",
				Esquema::campo( 'email' ),
				Esquema::POST_TYPE,
				Esquema::campo( 'email' ),
				Esquema::POST_TYPE
			);
			// phpcs:enable
		}

		return $sql;
	}

	/**
	 * Total de inscrições que atendem aos filtros.
	 *
	 * @param array<string, string> $filtros Filtros (ver sql_filtros()).
	 */
	public static function contar( array $filtros = array() ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- trecho preparado em sql_filtros().
		return (int) $wpdb->get_var( 'SELECT COUNT(*) ' . self::sql_filtros( $filtros ) );
	}

	/**
	 * IDs das inscrições que atendem aos filtros.
	 *
	 * @param array<string, string> $filtros  Filtros (ver sql_filtros()).
	 * @param int                   $limite   Máximo de IDs.
	 * @param int                   $deslocar Deslocamento.
	 * @param string                $ordem    Coluna: ID, post_title, post_date ou pontuacao.
	 * @param string                $direcao  ASC ou DESC.
	 * @return int[]
	 */
	public static function ids( array $filtros = array(), int $limite = 20, int $deslocar = 0, string $ordem = 'ID', string $direcao = 'DESC' ): array {
		global $wpdb;

		$direcao = 'ASC' === strtoupper( $direcao ) ? 'ASC' : 'DESC';
		$colunas = array(
			'ID'         => 'p.ID',
			'post_title' => 'p.post_title',
			'post_date'  => 'p.post_date',
			'pontuacao'  => $wpdb->prepare( '(SELECT CAST(pm.meta_value AS SIGNED) FROM ' . $wpdb->postmeta . ' pm WHERE pm.post_id = p.ID AND pm.meta_key = %s LIMIT 1)', Esquema::campo( 'pontuacao' ) ), // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		);
		$coluna  = $colunas[ $ordem ] ?? 'p.ID';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trecho preparado em sql_filtros(); coluna e direção vêm de lista fixa.
		$ids = $wpdb->get_col( 'SELECT p.ID ' . self::sql_filtros( $filtros ) . " ORDER BY {$coluna} {$direcao}, p.ID {$direcao}" . $wpdb->prepare( ' LIMIT %d OFFSET %d', max( 1, $limite ), max( 0, $deslocar ) ) );

		return array_map( 'intval', $ids );
	}

	/**
	 * Apaga de vez uma inscrição e a mídia dela.
	 *
	 * A mídia sai pelo hook before_delete_post (Plugin::antes_de_apagar),
	 * que vale também para exclusões feitas pela tela nativa do WordPress.
	 *
	 * @param int $post_id ID da inscrição.
	 * @return array{ok: bool, titulo: string, midias: int}
	 */
	public static function apagar( int $post_id ): array {
		$titulo = get_the_title( $post_id );

		if ( get_post_type( $post_id ) !== Esquema::POST_TYPE ) {
			return array(
				'ok'     => false,
				'titulo' => $titulo,
				'midias' => 0,
			);
		}

		$anexos = Midia::anexos_da_inscricao( $post_id );
		$ok     = (bool) wp_delete_post( $post_id, true );
		$midias = count( array_filter( $anexos, static fn( int $id ): bool => null === get_post( $id ) ) );

		return array(
			'ok'     => $ok,
			'titulo' => $titulo,
			'midias' => $midias,
		);
	}

	/**
	 * Monta FROM/WHERE da consulta de mídias por origem.
	 *
	 * @param string $origem Uma de ORIGENS_MIDIA.
	 */
	private static function sql_midias( string $origem ): string {
		global $wpdb;

		$base = "FROM {$wpdb->posts} a LEFT JOIN {$wpdb->posts} pai ON pai.ID = a.post_parent WHERE a.post_type = 'attachment'";

		switch ( $origem ) {
			case 'sem_autor':
				return $base . " AND NOT EXISTS ( SELECT 1 FROM {$wpdb->users} u WHERE u.ID = a.post_author )";

			case 'pai_apagado':
				return $base . ' AND a.post_parent > 0 AND pai.ID IS NULL';

			case 'nao_referenciadas':
				// Filhas de uma inscrição, mas que não são a capa nem uma imagem_N_publica dela.
				return $base . $wpdb->prepare( ' AND pai.post_type = %s', Esquema::POST_TYPE )
					. " AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} t WHERE t.post_id = pai.ID AND t.meta_key = '_thumbnail_id' AND t.meta_value = CAST(a.ID AS CHAR) )
					    AND NOT EXISTS ( SELECT 1 FROM {$wpdb->postmeta} i WHERE i.post_id = pai.ID AND i.meta_key LIKE 'imagem\\__\\_publica'
					        AND i.meta_value LIKE CONCAT('%\"attachment_id\":', a.ID, ',%') )";

			default:
				return $base . $wpdb->prepare( ' AND pai.post_type = %s', Esquema::POST_TYPE );
		}
	}

	/**
	 * Total de mídias de uma origem.
	 *
	 * @param string $origem Uma de ORIGENS_MIDIA.
	 */
	public static function contar_midias( string $origem ): int {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- SQL montado com valores fixos/preparados.
		return (int) $wpdb->get_var( 'SELECT COUNT(*) ' . self::sql_midias( $origem ) );
	}

	/**
	 * Página de mídias de uma origem (mais recentes primeiro).
	 *
	 * @param string $origem   Uma de ORIGENS_MIDIA.
	 * @param int    $limite   Itens por página.
	 * @param int    $deslocar Deslocamento.
	 * @return object[] Linhas com ID, post_date, post_mime_type, post_parent, post_author.
	 */
	public static function midias( string $origem, int $limite, int $deslocar ): array {
		global $wpdb;
		$sql = 'SELECT a.ID, a.post_date, a.post_mime_type, a.post_parent, a.post_author ' . self::sql_midias( $origem )
			. $wpdb->prepare( ' ORDER BY a.post_date DESC, a.ID DESC LIMIT %d OFFSET %d', max( 1, $limite ), max( 0, $deslocar ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared -- SQL montado com valores fixos/preparados.
		return (array) $wpdb->get_results( $sql );
	}

	/**
	 * Descreve de onde veio uma mídia, a partir do post_parent.
	 *
	 * @param int $anexo_id ID do anexo.
	 * @param int $pai_id   post_parent do anexo.
	 * @return array{texto: string, link: string}
	 */
	public static function origem_da_midia( int $anexo_id, int $pai_id ): array {
		if ( Midia::eh_imagem_padrao( $anexo_id ) ) {
			return array(
				'texto' => __( 'Imagem padrão compartilhada (inscrições que ocultam as imagens)', 'cedu-inscricoes-campanhas' ),
				'link'  => '',
			);
		}

		if ( $pai_id <= 0 ) {
			return array(
				'texto' => __( 'Upload avulso (sem post pai)', 'cedu-inscricoes-campanhas' ),
				'link'  => '',
			);
		}

		$pai = get_post( $pai_id );
		if ( ! $pai ) {
			return array(
				/* translators: %d: ID do post pai. */
				'texto' => sprintf( __( 'Post pai #%d (já apagado)', 'cedu-inscricoes-campanhas' ), $pai_id ),
				'link'  => '',
			);
		}

		$link = (string) get_edit_post_link( $pai_id, 'raw' );

		if ( Esquema::POST_TYPE !== $pai->post_type ) {
			return array(
				/* translators: 1: título do post, 2: ID, 3: tipo de post. */
				'texto' => sprintf( __( 'Anexo de "%1$s" (#%2$d, %3$s)', 'cedu-inscricoes-campanhas' ), $pai->post_title, $pai_id, $pai->post_type ),
				'link'  => $link,
			);
		}

		$titulo = '' !== $pai->post_title ? $pai->post_title : '#' . $pai_id;

		if ( (int) get_post_thumbnail_id( $pai_id ) === $anexo_id ) {
			return array(
				/* translators: %s: título da inscrição. */
				'texto' => sprintf( __( 'Capa da inscrição "%s"', 'cedu-inscricoes-campanhas' ), $titulo ),
				'link'  => $link,
			);
		}

		for ( $n = 1; $n <= Esquema::total_imagens_publicas(); $n++ ) {
			$img = json_decode( (string) get_post_meta( $pai_id, Esquema::meta_imagem_publica( $n ), true ), true );
			if ( ! is_array( $img ) || (int) ( $img['attachment_id'] ?? 0 ) !== $anexo_id ) {
				continue;
			}

			$extra = '';
			if ( ! empty( $img['source'] ) ) {
				$extra = isset( $img['sourceIndex'] )
					/* translators: 1: campo de origem, 2: índice. */
					? sprintf( __( ' (campo original: %1$s, item %2$d)', 'cedu-inscricoes-campanhas' ), $img['source'], (int) $img['sourceIndex'] )
					/* translators: %s: campo de origem. */
					: sprintf( __( ' (campo original: %s)', 'cedu-inscricoes-campanhas' ), $img['source'] );
			}

			return array(
				/* translators: 1: número da imagem, 2: título da inscrição, 3: detalhe da origem. */
				'texto' => sprintf( __( 'Imagem %1$d pública da inscrição "%2$s"%3$s', 'cedu-inscricoes-campanhas' ), $n, $titulo, $extra ),
				'link'  => $link,
			);
		}

		return array(
			/* translators: 1: título da inscrição, 2: ID. */
			'texto' => sprintf( __( 'Anexo de "%1$s" (#%2$d), não referenciado nas metas atuais', 'cedu-inscricoes-campanhas' ), $titulo, $pai_id ),
			'link'  => $link,
		);
	}
}
