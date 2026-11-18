<?php
/**
 * Configurações do plugin (options) e seus valores padrão.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

/**
 * Leitura e gravação das options do plugin.
 */
final class Config {

	/** Hash SHA-256 da chave da API (a chave em si nunca é gravada). */
	public const OPCAO_CHAVE_HASH = 'cedu_inscricoes_api_key_hash';

	/** Recebimento de envios pela API: '1' aberto, '0' fechado. */
	public const OPCAO_RECEBIMENTO = 'cedu_inscricoes_recebimento';

	/** Mensagem devolvida ao formulário com o recebimento fechado. */
	public const OPCAO_MENSAGEM_FECHADO = 'cedu_inscricoes_mensagem_fechado';

	/** Slug do termo de edição atribuído às inscrições novas. */
	public const OPCAO_EDICAO = 'cedu_inscricoes_edicao_padrao';

	/** Usuário gravado como autor das inscrições e das mídias baixadas. */
	public const OPCAO_AUTOR = 'cedu_inscricoes_autor_id';

	/** URL de origem da imagem padrão (inscrições que ocultam as imagens). */
	public const OPCAO_IMAGEM_URL = 'cedu_inscricoes_imagem_padrao_url';

	/** ID do anexo único da imagem padrão, reaproveitado por todas as inscrições. */
	public const OPCAO_IMAGEM_ID = 'cedu_inscricoes_imagem_padrao_id';

	/** Nomes de campos recebidos e não configurados (só nomes, nunca valores). */
	public const OPCAO_CAMPOS_IGNORADOS = 'cedu_inscricoes_campos_ignorados';

	/** Constante opcional no wp-config.php com a chave da API. */
	public const CONSTANTE_CHAVE = 'CEDU_INSCRICOES_API_KEY';

	/** Valores padrão. */
	public const EDICAO_PADRAO     = '2026';
	public const AUTOR_PADRAO      = 1;
	public const IMAGEM_URL_PADRAO = 'https://educacao.cemaden.gov.br/wp-content/uploads/2026/02/Cidades-sem-Risco-Versao-final.png';

	/** Endereços de referência. */
	public const URL_REPOSITORIO = 'https://github.com/alxmqsjr/cedu-inscricoes-campanhas';
	public const URL_DOC_CAMPOS  = 'https://github.com/alxmqsjr/cedu-inscricoes-campanhas/blob/main/docs/CAMPOS.md';

	/**
	 * Todas as options criadas pelo plugin (usado no uninstall).
	 *
	 * @return string[]
	 */
	public static function opcoes(): array {
		return array(
			self::OPCAO_CHAVE_HASH,
			self::OPCAO_RECEBIMENTO,
			self::OPCAO_MENSAGEM_FECHADO,
			self::OPCAO_EDICAO,
			self::OPCAO_AUTOR,
			self::OPCAO_IMAGEM_URL,
			self::OPCAO_IMAGEM_ID,
			self::OPCAO_CAMPOS_IGNORADOS,
		);
	}

	/** Diz se a API está aceitando envios. */
	public static function recebimento_aberto(): bool {
		return '0' !== (string) get_option( self::OPCAO_RECEBIMENTO, '1' );
	}

	/** Mensagem para o formulário quando o recebimento está fechado. */
	public static function mensagem_fechado(): string {
		$msg = get_option( self::OPCAO_MENSAGEM_FECHADO, '' );
		$msg = is_string( $msg ) ? trim( $msg ) : '';
		return '' !== $msg ? $msg : __( 'O recebimento de inscrições está encerrado.', 'cedu-inscricoes-campanhas' );
	}

	/** Slug da edição atribuída às inscrições novas ('' desativa). */
	public static function edicao(): string {
		$valor = get_option( self::OPCAO_EDICAO, self::EDICAO_PADRAO );
		return sanitize_title( is_string( $valor ) ? $valor : '' );
	}

	/**
	 * ID do autor das inscrições: sempre um administrador. Se o usuário
	 * configurado não existir mais ou deixar de ser administrador, usa o
	 * administrador de menor ID.
	 */
	public static function autor(): int {
		$id = (int) get_option( self::OPCAO_AUTOR, self::AUTOR_PADRAO );
		if ( self::eh_administrador( $id ) ) {
			return $id;
		}
		$admins = get_users(
			array(
				'role'    => 'administrator',
				'orderby' => 'ID',
				'order'   => 'ASC',
				'number'  => 1,
				'fields'  => 'ID',
			)
		);
		return (int) ( $admins[0] ?? 0 );
	}

	/**
	 * Diz se o usuário existe e tem o papel de administrador.
	 *
	 * @param int $id ID do usuário.
	 */
	public static function eh_administrador( int $id ): bool {
		$usuario = $id > 0 ? get_userdata( $id ) : false;
		return $usuario && in_array( 'administrator', (array) $usuario->roles, true );
	}

	/** URL de origem da imagem padrão. */
	public static function imagem_padrao_url(): string {
		$url = get_option( self::OPCAO_IMAGEM_URL, self::IMAGEM_URL_PADRAO );
		$url = is_string( $url ) && '' !== $url ? $url : self::IMAGEM_URL_PADRAO;
		/**
		 * Filtra a URL da imagem padrão usada quando a inscrição não autoriza
		 * a exposição das imagens.
		 *
		 * @param string $url URL da imagem.
		 */
		return (string) apply_filters( 'cedu_inscricoes_imagem_padrao_url', esc_url_raw( $url ) );
	}
}
