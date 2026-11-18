<?php
/**
 * Mídia das inscrições: download, imagem padrão compartilhada e limpeza.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

/**
 * Operações de mídia.
 */
final class Midia {

	/** Meta que marca o anexo único da imagem padrão (nunca é apagado pelo plugin). */
	public const META_IMAGEM_PADRAO = '_cedu_inscricoes_imagem_padrao';

	/** Tempo máximo de download de cada imagem, em segundos. */
	private const TIMEOUT_DOWNLOAD = 60;

	/** Tamanho máximo de cada imagem baixada, em bytes (20 MB). */
	private const TAMANHO_MAXIMO = 20 * 1024 * 1024;

	/**
	 * Baixa uma imagem para a Biblioteca de Mídia.
	 *
	 * @param string $url     URL de origem.
	 * @param int    $post_id Post pai do anexo (0 para nenhum).
	 * @param string $titulo  Título do anexo; vazio mantém o nome do arquivo.
	 * @return int ID do anexo, ou 0 em caso de falha.
	 */
	public static function baixar( string $url, int $post_id, string $titulo = '' ): int {
		if ( '' === $url ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Limita o tamanho baixado (evita encher o disco com um arquivo enorme).
		$limitar = static function ( array $args ): array {
			$args['limit_response_size'] = self::TAMANHO_MAXIMO;
			return $args;
		};
		add_filter( 'http_request_args', $limitar );
		$tmp = download_url( $url, self::TIMEOUT_DOWNLOAD );
		remove_filter( 'http_request_args', $limitar );

		if ( is_wp_error( $tmp ) ) {
			return 0;
		}

		// Só imagens de verdade (conferido pelo conteúdo, não pela extensão), e
		// nada que tenha batido no limite (arquivo truncado).
		if ( ! wp_get_image_mime( $tmp ) || (int) filesize( $tmp ) >= self::TAMANHO_MAXIMO ) {
			wp_delete_file( $tmp );
			return 0;
		}

		$caminho = (string) wp_parse_url( $url, PHP_URL_PATH );
		$nome    = sanitize_file_name( basename( urldecode( $caminho ) ) );
		if ( '' === $nome || strlen( $nome ) > 100 || ! str_contains( $nome, '.' ) ) {
			$nome = 'capa-' . $post_id . '-' . time() . '.jpg';
		}

		// media_handle_sideload() grava título vazio se receber '' em $desc;
		// com null ele usa o nome do arquivo.
		$titulo = sanitize_text_field( trim( $titulo ) );
		$desc   = '' !== $titulo ? $titulo : null;

		$id = media_handle_sideload(
			array(
				'name'     => $nome,
				'tmp_name' => $tmp,
			),
			$post_id,
			$desc
		);

		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return 0;
		}

		// O título vai explícito (o WP pode preferir o nome do arquivo ou o
		// IPTC da imagem). O autor também: a API chega sem usuário logado e,
		// sem isso, a mídia ficaria "(sem autor)" na Biblioteca.
		$dados = array(
			'ID'          => (int) $id,
			'post_author' => Config::autor(),
		);
		if ( null !== $desc ) {
			$dados['post_title'] = $desc;
		}
		wp_update_post( wp_slash( $dados ) ); // wp_update_post() espera dados com barras.

		return (int) $id;
	}

	/**
	 * Anexo único da imagem padrão (inscrições que ocultam as imagens), baixado uma só vez e
	 * reaproveitado por todas as inscrições. Baixa de novo só se o anexo
	 * sumir ou se a URL configurada mudar.
	 *
	 * @return int ID do anexo, ou 0 se não foi possível obtê-lo.
	 */
	public static function imagem_padrao(): int {
		$url = Config::imagem_padrao_url();
		$id  = (int) get_option( Config::OPCAO_IMAGEM_ID, 0 );

		if ( $id > 0 && 'attachment' === get_post_type( $id ) && get_post_meta( $id, self::META_IMAGEM_PADRAO, true ) === $url ) {
			return $id;
		}

		$id = self::baixar( $url, 0, __( 'Imagem padrão das inscrições', 'cedu-inscricoes-campanhas' ) );
		if ( $id ) {
			update_post_meta( $id, self::META_IMAGEM_PADRAO, $url );
			update_option( Config::OPCAO_IMAGEM_ID, $id, false );
		}
		return $id;
	}

	/**
	 * Diz se o anexo é a imagem padrão compartilhada (atual ou anterior).
	 *
	 * @param int $id ID do anexo.
	 */
	public static function eh_imagem_padrao( int $id ): bool {
		return $id > 0 && '' !== (string) get_post_meta( $id, self::META_IMAGEM_PADRAO, true );
	}

	/**
	 * Anexos que o importador criou para a inscrição e que devem ser
	 * substituídos num reenvio: a capa e as imagens públicas, desde que
	 * pertençam à própria inscrição (post_parent) e não sejam a imagem padrão.
	 * Uma imagem escolhida à mão na Biblioteca nunca entra aqui.
	 *
	 * @param int $post_id ID da inscrição.
	 * @return int[]
	 */
	public static function anexos_do_importador( int $post_id ): array {
		$ids = array( (int) get_post_thumbnail_id( $post_id ) );

		foreach ( Esquema::campos_imagem_publica() as $campo ) {
			$img = json_decode( (string) get_post_meta( $post_id, $campo, true ), true );
			if ( is_array( $img ) && ! empty( $img['attachment_id'] ) ) {
				$ids[] = (int) $img['attachment_id'];
			}
		}

		return array_values(
			array_filter(
				array_unique( $ids ),
				static fn( int $id ): bool => self::pertence_a( $id, $post_id )
			)
		);
	}

	/**
	 * Todos os anexos da inscrição (post_parent), exceto a imagem padrão.
	 *
	 * @param int $post_id ID da inscrição.
	 * @return int[]
	 */
	public static function anexos_da_inscricao( int $post_id ): array {
		$ids = get_children(
			array(
				'post_parent' => $post_id,
				'post_type'   => 'attachment',
				'post_status' => 'any',
				'fields'      => 'ids',
				'numberposts' => -1,
			)
		);

		return array_values(
			array_filter(
				array_map( 'intval', (array) $ids ),
				static fn( int $id ): bool => ! self::eh_imagem_padrao( $id )
			)
		);
	}

	/**
	 * Apaga anexos de vez (arquivos incluídos).
	 *
	 * @param int[] $ids IDs dos anexos.
	 * @return int Quantos foram apagados.
	 */
	public static function apagar( array $ids ): int {
		$apagados = 0;
		foreach ( $ids as $id ) {
			if ( self::eh_imagem_padrao( (int) $id ) ) {
				continue;
			}
			if ( wp_delete_attachment( (int) $id, true ) ) {
				++$apagados;
			}
		}
		return $apagados;
	}

	/**
	 * Diz se o anexo existe, é filho da inscrição e não é a imagem padrão.
	 *
	 * @param int $id      ID do anexo.
	 * @param int $post_id ID da inscrição.
	 */
	private static function pertence_a( int $id, int $post_id ): bool {
		if ( $id <= 0 ) {
			return false;
		}
		$anexo = get_post( $id );
		return $anexo
			&& 'attachment' === $anexo->post_type
			&& (int) $anexo->post_parent === $post_id
			&& ! self::eh_imagem_padrao( $id );
	}
}
