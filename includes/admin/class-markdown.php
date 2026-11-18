<?php
/**
 * Conversor mínimo de Markdown para exibir a documentação do plugin no painel.
 *
 * Cobre só o que os arquivos de docs/ usam: títulos, parágrafos, listas,
 * citações, tabelas, blocos de código, `código`, **negrito**, *itálico* e
 * links. Todo o texto é escapado antes de qualquer marcação ser aplicada.
 *
 * @package CemadenInscricoes
 */

namespace CemadenInscricoes;

defined( 'ABSPATH' ) || exit;

/**
 * Markdown → HTML (subconjunto).
 */
final class Markdown {

	/**
	 * Converte um texto Markdown em HTML seguro.
	 *
	 * @param string $texto Markdown.
	 */
	public static function html( string $texto ): string {
		$linhas = preg_split( '/\r\n|\r|\n/', $texto );
		$html   = '';
		$total  = count( $linhas );

		for ( $i = 0; $i < $total; $i++ ) {
			$linha = $linhas[ $i ];

			// Bloco de código.
			if ( str_starts_with( ltrim( $linha ), '```' ) ) {
				$codigo = array();
				for ( ++$i; $i < $total && ! str_starts_with( ltrim( $linhas[ $i ] ), '```' ); $i++ ) {
					$codigo[] = $linhas[ $i ];
				}
				$html .= '<pre><code>' . esc_html( implode( "\n", $codigo ) ) . '</code></pre>';
				continue;
			}

			if ( '' === trim( $linha ) ) {
				continue;
			}

			// Títulos (# vira h2: a tela já tem o h1).
			if ( preg_match( '/^(#{1,4})\s+(.*)$/', $linha, $m ) ) {
				$nivel = min( 5, strlen( $m[1] ) + 1 );
				$html .= "<h{$nivel}>" . self::inline( $m[2] ) . "</h{$nivel}>";
				continue;
			}

			// Tabela.
			if ( str_starts_with( trim( $linha ), '|' ) ) {
				$tabela = array();
				for ( ; $i < $total && str_starts_with( trim( $linhas[ $i ] ), '|' ); $i++ ) {
					$tabela[] = trim( $linhas[ $i ] );
				}
				--$i;
				$html .= self::tabela( $tabela );
				continue;
			}

			// Citação.
			if ( str_starts_with( $linha, '>' ) ) {
				$partes = array();
				for ( ; $i < $total && str_starts_with( $linhas[ $i ], '>' ); $i++ ) {
					$partes[] = ltrim( substr( $linhas[ $i ], 1 ) );
				}
				--$i;
				$html .= '<blockquote><p>' . self::inline( implode( ' ', $partes ) ) . '</p></blockquote>';
				continue;
			}

			// Listas.
			if ( preg_match( '/^(\s*)([-*]|\d+\.)\s+/', $linha, $m ) ) {
				$ordenada = ctype_digit( rtrim( $m[2], '.' ) );
				$itens    = array();
				for ( ; $i < $total && preg_match( '/^\s*([-*]|\d+\.)\s+(.*)$/', $linhas[ $i ], $mi ); $i++ ) {
					$itens[] = '<li>' . self::inline( $mi[2] ) . '</li>';
				}
				--$i;
				$tag   = $ordenada ? 'ol' : 'ul';
				$html .= "<{$tag}>" . implode( '', $itens ) . "</{$tag}>";
				continue;
			}

			// Parágrafo: junta as linhas seguidas.
			$paragrafo = array( $linha );
			while ( $i + 1 < $total && '' !== trim( $linhas[ $i + 1 ] ) && ! preg_match( '/^(#{1,4}\s|```|\||>|\s*([-*]|\d+\.)\s)/', $linhas[ $i + 1 ] ) ) {
				$paragrafo[] = $linhas[ ++$i ];
			}
			$html .= '<p>' . self::inline( implode( ' ', $paragrafo ) ) . '</p>';
		}

		return $html;
	}

	/**
	 * Tabela Markdown (a segunda linha é o separador).
	 *
	 * @param string[] $linhas Linhas da tabela.
	 */
	private static function tabela( array $linhas ): string {
		$celulas = static fn( string $l ): array => array_map( 'trim', explode( '|', trim( $l, '|' ) ) );
		$html    = '<table class="widefat striped"><thead><tr>';
		foreach ( $celulas( $linhas[0] ) as $c ) {
			$html .= '<th>' . self::inline( $c ) . '</th>';
		}
		$html .= '</tr></thead><tbody>';
		foreach ( array_slice( $linhas, 2 ) as $linha ) {
			$html .= '<tr>';
			foreach ( $celulas( $linha ) as $c ) {
				$html .= '<td>' . self::inline( $c ) . '</td>';
			}
			$html .= '</tr>';
		}
		return $html . '</tbody></table>';
	}

	/**
	 * Marcação dentro da linha. O texto é escapado primeiro; o código entre
	 * crases é separado antes para não receber outras marcações.
	 *
	 * @param string $texto Texto.
	 */
	private static function inline( string $texto ): string {
		$codigos = array();
		$texto   = (string) preg_replace_callback(
			'/`([^`]+)`/',
			static function ( array $m ) use ( &$codigos ): string {
				$codigos[] = '<code>' . esc_html( $m[1] ) . '</code>';
				return "\x00" . ( count( $codigos ) - 1 ) . "\x00";
			},
			$texto
		);

		$texto = esc_html( $texto );

		// Links: só http(s) viram link; caminhos relativos ficam como texto.
		$texto = (string) preg_replace_callback(
			'/\[([^\]]+)\]\(([^)\s]+)\)/',
			static function ( array $m ): string {
				$url = html_entity_decode( $m[2], ENT_QUOTES );
				if ( preg_match( '#^https?://#i', $url ) ) {
					return '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . $m[1] . '</a>';
				}
				return $m[1];
			},
			$texto
		);

		$texto = (string) preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $texto );
		$texto = (string) preg_replace( '/(?<![\w*])\*(?!\s)(.+?)(?<!\s)\*(?![\w*])/', '<em>$1</em>', $texto );

		return (string) preg_replace_callback(
			"/\x00(\d+)\x00/",
			static fn( array $m ): string => $codigos[ (int) $m[1] ] ?? '',
			$texto
		);
	}
}
