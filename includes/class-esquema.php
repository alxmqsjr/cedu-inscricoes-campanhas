<?php
/**
 * Esquema dos dados de uma inscrição, lido de config/campos.php.
 *
 * Nenhum outro arquivo do plugin tem nomes de campos do formulário escritos:
 * todos perguntam a esta classe. Para mudar campos, edite config/campos.php
 * (ver docs/CAMPOS.md).
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

/**
 * Acesso ao arquivo de campos.
 */
final class Esquema {

	/** Post type gerenciado. */
	public const POST_TYPE = 'inscricoes';

	/** Taxonomias registradas pelo tema (ou pelo fallback deste plugin). */
	public const TAX_STATUS = 'status_inscricao';
	public const TAX_EDICAO = 'edicao_inscricao';

	/** Tipos de campo aceitos no arquivo de configuração. */
	public const TIPOS = array( 'texto', 'numero', 'lista', 'objeto', 'evidencia' );

	/**
	 * Configuração carregada (cache da requisição).
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $config = null;

	/**
	 * Configuração completa, validada.
	 *
	 * @return array<string, mixed>
	 */
	public static function config(): array {
		if ( null !== self::$config ) {
			return self::$config;
		}

		$config = require DIR . '/config/campos.php';

		/**
		 * Filtra os campos do formulário (mesma estrutura de config/campos.php).
		 * Permite ajustar os campos a partir do tema, sem editar o plugin.
		 *
		 * @param array<string, mixed> $config Configuração.
		 */
		$config = apply_filters( 'cedu_inscricoes_campos', is_array( $config ) ? $config : array() );

		self::$config = self::validar( is_array( $config ) ? $config : array() );
		return self::$config;
	}

	/** Descarta o cache (usado nos testes). */
	public static function recarregar(): void {
		self::$config = null;
	}

	/**
	 * Normaliza a configuração: só nomes válidos, valores padrão nos papéis.
	 *
	 * @param array<string, mixed> $c Configuração bruta.
	 * @return array<string, mixed>
	 */
	private static function validar( array $c ): array {
		$papeis = is_array( $c['papeis'] ?? null ) ? $c['papeis'] : array();

		$id        = is_array( $papeis['id'] ?? null ) ? $papeis['id'] : array();
		$protecao  = is_array( $papeis['protecao'] ?? null ) ? $papeis['protecao'] : array();
		$enviado   = is_array( $papeis['enviado_em'] ?? null ) ? $papeis['enviado_em'] : array();
		$imagens   = is_array( $c['imagens_publicas'] ?? null ) ? $c['imagens_publicas'] : array();
		$resultado = array(
			'papeis'           => array(
				'id'          => array(
					'campo' => self::nome( $id['campo'] ?? '' ),
					'meta'  => self::nome( $id['meta'] ?? '' ),
				),
				'titulo'      => self::nome( $papeis['titulo'] ?? '' ),
				'capa'        => self::nome( $papeis['capa'] ?? '' ),
				'protecao'    => array(
					'campo'  => self::nome( $protecao['campo'] ?? '' ),
					'oculta' => is_scalar( $protecao['oculta'] ?? null ) ? (string) $protecao['oculta'] : '',
				),
				'enviado_em'  => array(
					'campo'    => self::nome( $enviado['campo'] ?? '' ),
					'subcampo' => self::nome( $enviado['subcampo'] ?? '' ),
					'meta'     => self::nome( $enviado['meta'] ?? '' ),
				),
				'nome'        => self::nome( $papeis['nome'] ?? '' ),
				'email'       => self::nome( $papeis['email'] ?? '' ),
				'instituicao' => self::nome( $papeis['instituicao'] ?? '' ),
				'municipio'   => self::nome( $papeis['municipio'] ?? '' ),
				'estado'      => self::nome( $papeis['estado'] ?? '' ),
				'pontuacao'   => self::nome( $papeis['pontuacao'] ?? '' ),
			),
			'imagens_publicas' => array(
				'prefixo'    => self::nome( $imagens['prefixo'] ?? '' ),
				'sufixo'     => (string) preg_replace( '/[^A-Za-z0-9_-]/', '', (string) ( $imagens['sufixo'] ?? '' ) ),
				'quantidade' => max( 0, min( 20, (int) ( $imagens['quantidade'] ?? 0 ) ) ),
			),
			'apelidos'         => array(),
		);

		foreach ( self::TIPOS as $tipo ) {
			$lista              = is_array( $c[ $tipo ] ?? null ) ? $c[ $tipo ] : array();
			$resultado[ $tipo ] = array_values( array_unique( array_filter( array_map( array( self::class, 'nome' ), $lista ) ) ) );
		}

		$apelidos = is_array( $c['apelidos'] ?? null ) ? $c['apelidos'] : array();
		foreach ( $apelidos as $novo => $antigo ) {
			$novo   = self::nome( $novo );
			$antigo = self::nome( $antigo );
			if ( '' !== $novo && '' !== $antigo && $novo !== $antigo ) {
				$resultado['apelidos'][ $novo ] = $antigo;
			}
		}

		return $resultado;
	}

	/**
	 * Nome de campo/meta válido: letras, números, "_" e "-", sem começar com
	 * "_" (metas protegidas do WordPress), com até 100 caracteres.
	 *
	 * @param mixed $nome Valor.
	 * @return string Nome válido ou ''.
	 */
	public static function nome( $nome ): string {
		$nome = is_scalar( $nome ) ? (string) $nome : '';
		return preg_match( '/^[A-Za-z0-9-][A-Za-z0-9_-]{0,99}$/', $nome ) ? $nome : '';
	}

	/**
	 * Nome do campo com um papel simples ('' se não configurado).
	 *
	 * @param string $papel titulo, capa, nome, email, instituicao, municipio, estado ou pontuacao.
	 */
	public static function campo( string $papel ): string {
		$valor = self::config()['papeis'][ $papel ] ?? '';
		return is_string( $valor ) ? $valor : '';
	}

	/**
	 * Configuração de um papel composto (id, protecao, enviado_em).
	 *
	 * @param string $papel Papel.
	 * @return array<string, string>
	 */
	public static function papel( string $papel ): array {
		$valor = self::config()['papeis'][ $papel ] ?? array();
		return is_array( $valor ) ? $valor : array();
	}

	/**
	 * Valor gravado de um papel simples (nome, email, municipio…) numa inscrição.
	 *
	 * @param int    $post_id ID da inscrição.
	 * @param string $papel   Papel.
	 */
	public static function valor( int $post_id, string $papel ): string {
		$meta = 'id' === $papel ? self::meta_id() : self::campo( $papel );
		return '' !== $meta ? (string) get_post_meta( $post_id, $meta, true ) : '';
	}

	/** Meta que guarda o identificador do envio. */
	public static function meta_id(): string {
		return self::papel( 'id' )['meta'] ?? '';
	}

	/**
	 * Campos de um tipo.
	 *
	 * @param string $tipo Um de TIPOS.
	 * @return string[]
	 */
	public static function campos( string $tipo ): array {
		return self::config()[ $tipo ] ?? array();
	}

	/** Quantidade de imagens públicas. */
	public static function total_imagens_publicas(): int {
		return (int) self::config()['imagens_publicas']['quantidade'];
	}

	/**
	 * Nome da meta (e do campo) da imagem pública N.
	 *
	 * @param int $n Número da imagem.
	 */
	public static function meta_imagem_publica( int $n ): string {
		$c = self::config()['imagens_publicas'];
		return $c['prefixo'] . $n . $c['sufixo'];
	}

	/**
	 * Metas de todas as imagens públicas.
	 *
	 * @return string[]
	 */
	public static function campos_imagem_publica(): array {
		$campos = array();
		for ( $n = 1; $n <= self::total_imagens_publicas(); $n++ ) {
			$campos[] = self::meta_imagem_publica( $n );
		}
		return $campos;
	}

	/**
	 * Apelidos: nome novo no JSON => nome configurado.
	 *
	 * @return array<string, string>
	 */
	public static function apelidos(): array {
		return self::config()['apelidos'];
	}

	/**
	 * Todas as metas gravadas pelo importador. Num reenvio, são apagadas
	 * antes de regravar (campo que deixou de vir some).
	 *
	 * @return string[]
	 */
	public static function todas_as_metas(): array {
		$metas = array( self::meta_id(), self::papel( 'enviado_em' )['meta'] ?? '' );
		foreach ( self::TIPOS as $tipo ) {
			$metas = array_merge( $metas, self::campos( $tipo ) );
		}
		return array_values( array_unique( array_filter( array_merge( $metas, self::campos_imagem_publica() ) ) ) );
	}

	/**
	 * Campos do JSON que o importador reconhece (os demais são ignorados e
	 * registrados em Informações).
	 *
	 * @return string[]
	 */
	public static function campos_conhecidos(): array {
		$campos = array(
			self::papel( 'id' )['campo'] ?? '',
			self::campo( 'titulo' ),
			self::campo( 'capa' ),
			self::papel( 'protecao' )['campo'] ?? '',
			self::papel( 'enviado_em' )['campo'] ?? '',
		);
		foreach ( self::TIPOS as $tipo ) {
			$campos = array_merge( $campos, self::campos( $tipo ) );
		}
		$campos = array_merge( $campos, self::campos_imagem_publica(), array_keys( self::apelidos() ) );
		return array_values( array_unique( array_filter( $campos ) ) );
	}
}
