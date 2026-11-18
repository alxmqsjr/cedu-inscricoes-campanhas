<?php
/**
 * CAMPOS DO FORMULÁRIO: o único arquivo a editar quando o JSON mudar.
 *
 * Guia completo, com exemplos: docs/CAMPOS.md
 *
 * Regras rápidas
 * - A chave à esquerda é o nome do campo no JSON. Exceto onde indicado, a
 *   meta gravada no post tem o MESMO nome, e é esse nome que o tema lê
 *   (single-inscricoes.php, archive-inscricoes.php, Portal de Resultados).
 * - Campo novo: acrescente o nome na lista do tipo certo.
 * - Campo removido do formulário: pode deixar aqui (ele só deixa de chegar).
 *   Se apagar a linha, as inscrições antigas mantêm a meta, mas um reenvio
 *   delas não a limpa mais (só os campos listados aqui são limpos).
 * - Campo renomeado no formulário: NÃO renomeie aqui. Acrescente um apelido
 *   em 'apelidos' ('nomeNovo' => 'nomeAntigo'): a meta continua com o nome
 *   antigo e nem o tema nem as inscrições antigas precisam mudar.
 * - Campo que chegar e não estiver aqui é ignorado, e o nome dele aparece em
 *   Inscrições → Informações ("Campos recebidos e não configurados").
 * - Não use nomes que comecem com "_" (reservados ao WordPress).
 *
 * Edite no repositório do plugin, não direto no servidor: uma atualização
 * do plugin substitui este arquivo. Alternativa sem editar o plugin: o
 * filtro 'cedu_inscricoes_campos' (ver docs/CAMPOS.md).
 *
 * @package CemadenInscricoes
 */

defined( 'ABSPATH' ) || exit;

return array(

	/*
	 * Campos com papel especial na importação.
	 */
	'papeis'           => array(
		// Identificador único do envio. Reenvio com o mesmo valor atualiza a
		// inscrição em vez de criar outra. Gravado na meta indicada.
		'id'           => array(
			'campo' => 'id',
			'meta'  => 'firebase_id',
		),

		// Vira o título do post (não é gravado como meta).
		'titulo'       => 'tituloCampanha',

		// URL da imagem de capa (baixada e definida como imagem de destaque).
		'capa'         => 'imagemCapaUrl',

		// Quando 'campo' tiver o valor 'oculta', a capa é a imagem padrão
		// (Configurações) e as imagens do participante não viram capa.
		'protecao'     => array(
			'campo'  => 'termoImagemProtecaoOpcao',
			'oculta' => 'opcao3',
		),

		// Data do envio: objeto com o número de segundos (formato Firebase).
		'enviado_em'   => array(
			'campo'    => 'submittedAt',
			'subcampo' => 'seconds',
			'meta'     => 'submitted_at',
		),

		// Campos usados nas telas do painel (colunas, busca e duplicadas).
		'nome'         => 'nomeCompleto',
		'email'        => 'email',
		'instituicao'  => 'nomeInstituicao',
		'municipio'    => 'municipio',
		'estado'       => 'estado',
		'pontuacao'    => 'pontuacao',
	),

	/*
	 * Texto (string). Gravado com sanitize_textarea_field().
	 */
	'texto'            => array(
		// Identificação pessoal.
		'nomeCompleto', 'nomeSocial', 'identidadeGenero', 'identidadeGeneroOutro', 'racaCor', 'cpf',
		'cargoFuncao', 'cargoFuncaoOutro', 'telefone', 'email', 'redeNosPerifricos', 'redeCemadenEducacao',
		// Proponente.
		'tipoProponente', 'tipoProponenteOutrasDetalhe', 'nomeInstituicao', 'obsIES', 'cnpjStatus',
		'cnpjNumero', 'inep', 'emailInstitucional',
		// Localização.
		'estado', 'municipio', 'cep', 'temCEP', 'bairro', 'comunidade', 'logradouro', 'numeroImovel',
		'pontoReferencia',
		// Campanha.
		'paraQue', 'comQuem', 'paraQuem', 'onde', 'como', 'quando', 'riscosOutro', 'grupoOrganizadorOutro',
		'parceriasOutro', 'alcanceSocialOutro', 'descricaoUso', 'acoesOutro', 'dadosOutro', 'materiaisOutro',
		'resultadosOutro',
		// Reflexão.
		'reflexaoFinal', 'reflexaoDeuCerto', 'reflexaoMelhorar', 'reflexaoContinuidade',
		// Imagem de capa e proteção (gravados também como meta).
		'imagemCapaUrl', 'termoImagemProtecaoOpcao',
	),

	/*
	 * Número inteiro.
	 */
	'numero'           => array(
		'pontuacao',
	),

	/*
	 * Lista simples (array). Gravada como JSON; ignorada se não vier como array.
	 */
	'lista'            => array(
		'eixosTematicos', 'riscosDesastres', 'diversidadeInclusao', 'dadosUtilizados', 'resultados',
		'linksMateriais', 'linksAcoes', 'tipoProponenteSegmento',
	),

	/*
	 * Objeto ou lista de objetos. Gravado como JSON (textos internos sanitizados).
	 */
	'objeto'           => array(
		'grupoOrganizador', 'parcerias', 'alcanceSocial', 'materiais', 'acoes', 'pontuacaoDetalhada',
	),

	/*
	 * Evidências: lista de { "url": "…", "caption": "…" }. Só URL e legenda
	 * são gravadas; as imagens NÃO são baixadas.
	 */
	'evidencia'        => array(
		'evidenciasAcoes', 'evidenciasGrupoOrganizador', 'evidenciasParcerias', 'evidenciasAlcanceSocial',
		'evidenciasDiversidade', 'evidenciasMateriais', 'evidenciasDados',
	),

	/*
	 * Imagens públicas: campos {prefixo}1{sufixo} … {prefixo}N{sufixo}, cada um
	 * { "url": "…", "caption": "…", "source": "evidenciasAcoes", "sourceIndex": 0 }.
	 * São baixadas para a Biblioteca de Mídia.
	 */
	'imagens_publicas' => array(
		'prefixo'    => 'imagem_',
		'sufixo'     => '_publica',
		'quantidade' => 5,
	),

	/*
	 * Apelidos: 'nome novo no JSON' => 'nome configurado acima'.
	 * Exemplo: o formulário passou a mandar "titulo" em vez de "tituloCampanha":
	 *     'titulo' => 'tituloCampanha',
	 */
	'apelidos'         => array(),
);
