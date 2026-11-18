# Campos do formulário

Guia para quem precisa adaptar o plugin a um formulário que mudou: campos novos, removidos ou renomeados.

**Resumo:** tudo o que depende dos nomes dos campos do JSON está em **um único arquivo**, [`config/campos.php`](../config/campos.php). Nenhum outro arquivo do plugin tem nomes de campos escritos. Para exibir um campo novo no site, ajuste também o tema (`single-inscricoes.php`).

> **Edite no repositório, não no servidor.** Uma atualização do plugin substitui `config/campos.php`. Faça a mudança aqui, publique uma nova versão e instale-a. Se preferir não mexer no plugin, use o [filtro](#alternativa-filtro-no-tema).

## Como o plugin usa o arquivo

O JSON que chega é um objeto. Para cada campo:

1. Se o nome estiver em `apelidos`, ele é trocado pelo nome configurado.
2. Se o nome estiver em alguma lista (`texto`, `numero`, `lista`, `objeto`, `evidencia`) ou em `papeis`, o valor é tratado conforme o tipo e gravado como **meta do post com o mesmo nome**.
3. Se não estiver em lugar nenhum, é **ignorado**, e o nome aparece em *Inscrições → Informações → Campos recebidos e não configurados*. Só o nome é registrado, nunca o valor.

O tema lê as metas pelo nome (`get_post_meta( $id, 'municipio', true )`). Por isso, **o nome da meta é o contrato entre o plugin e o tema**.

## Tipos

| Lista | Aceita | Grava |
|---|---|---|
| `texto` | string, número ou booleano | texto sem HTML (`sanitize_textarea_field`) |
| `numero` | número ou string numérica | inteiro |
| `lista` | array | JSON (textos internos sem HTML); ignorado se não vier array |
| `objeto` | qualquer valor | JSON (textos internos sem HTML) |
| `evidencia` | lista de `{ "url", "caption" }` | JSON só com `url`, `caption` e `local: false`; as imagens **não** são baixadas |

As **imagens públicas** (`imagens_publicas`) são campos `{prefixo}N{sufixo}`, por exemplo `imagem_1_publica` a `imagem_5_publica`, cada um `{ "url", "caption", "source", "sourceIndex" }`. Elas são baixadas para a Biblioteca de Mídia e a meta fica assim:

```json
{"attachment_id":123,"url":"https://…/uploads/…","url_original":"https://…","caption":"…","source":"evidenciasAcoes","sourceIndex":0,"local":true}
```

Se o download falhar: `attachment_id` 0, `url` igual a `url_original` e `local` false.

## Papéis especiais (`papeis`)

| Papel | O que faz |
|---|---|
| `id` | `campo`: identificador único do envio. Reenvio com o mesmo valor **atualiza** a inscrição. `meta`: onde o valor é gravado (hoje `firebase_id`). |
| `titulo` | Vira o título do post. Não é gravado como meta. |
| `capa` | URL baixada e definida como imagem de destaque. Liste-o também em `texto` se o tema precisar da URL. |
| `protecao` | Quando `campo` tem o valor `oculta`, a capa é a imagem padrão das Configurações e as imagens do participante não viram capa. |
| `enviado_em` | Data do envio. `campo` + `subcampo` (ex.: `submittedAt.seconds`) gravado como inteiro em `meta`. Sem subcampo, use `''`. |
| `nome`, `email`, `instituicao`, `municipio`, `estado`, `pontuacao` | Usados só nas telas do painel: colunas, busca e "possíveis duplicadas". Devem apontar para campos que também estejam nas listas. |

Deixe um papel vazio (`''`) para desligá-lo.

## Receitas

### Campo novo

O formulário passou a mandar `"publicoAlvo": "Estudantes"`.

```php
'texto' => array(
	// …
	'publicoAlvo',
),
```

Para exibir no site, no `single-inscricoes.php`:

```php
$publico = get_post_meta( get_the_ID(), 'publicoAlvo', true );
if ( $publico ) {
	echo '<p>' . esc_html( $publico ) . '</p>';
}
```

Lista ou objeto (`"temas": ["a", "b"]`): coloque em `lista` ou `objeto` e leia com `json_decode( get_post_meta( … ), true )`.

### Campo removido

Nada é obrigatório: ele deixa de chegar e as inscrições antigas mantêm o valor. Se apagar a linha do arquivo, um **reenvio** de uma inscrição antiga deixa de limpar aquela meta (só os campos listados são limpos num reenvio).

### Campo renomeado

O formulário trocou `municipio` por `cidade`. **Não renomeie na lista.** Acrescente um apelido:

```php
'apelidos' => array(
	'cidade' => 'municipio',
),
```

O valor de `cidade` é gravado na meta `municipio`. O tema, as telas e as inscrições antigas continuam funcionando. Se os dois nomes vierem no mesmo envio, vale o configurado (`municipio`).

O mesmo vale para campos com papel: se `tituloCampanha` virar `titulo`, use `'titulo' => 'tituloCampanha'`.

### Identificador, título ou capa com outro nome

Também dá para mudar direto em `papeis`, mas o apelido é preferível, porque não muda o nome de nenhuma meta. Mude `papeis` quando o significado mudar, por exemplo outro campo passar a ser o identificador.

> Mudar `papeis.id.meta` faz as inscrições antigas não serem mais reconhecidas num reenvio: elas guardam o identificador na meta antiga.

### Mais ou menos imagens públicas

Altere `quantidade` em `imagens_publicas` (máximo 20). O tema precisa exibir as novas.

### Proteção de imagens com outro valor

Se a opção que oculta as imagens passar a ser `"naoAutorizo"`:

```php
'protecao' => array(
	'campo'  => 'termoImagemProtecaoOpcao',
	'oculta' => 'naoAutorizo',
),
```

## Regras dos nomes

- Letras, números, `_` e `-`, com até 100 caracteres.
- **Não podem começar com `_`**, que é reservado para metas internas do WordPress, como `_thumbnail_id`. Nomes inválidos são descartados em silêncio.
- Diferenciam maiúsculas de minúsculas: `Email` e `email` são campos diferentes.

## Alternativa: filtro no tema

Sem editar o plugin, no `functions.php` do tema:

```php
add_filter( 'cedu_inscricoes_campos', function ( array $campos ) {
	$campos['texto'][]            = 'publicoAlvo';
	$campos['apelidos']['cidade'] = 'municipio';
	return $campos;
} );
```

O filtro recebe o array de `config/campos.php` e deve devolvê-lo na mesma estrutura.

## Conferindo

1. Envie um JSON de teste, ou use `wp inscricoes importar teste.json`.
2. Em *Inscrições → Informações*, confira a tabela de campos e a lista de "campos não configurados".
3. Abra a inscrição de teste em *Gerenciar* e confira as metas, por exemplo com o plugin *Show Post Meta*.
4. Apague a inscrição de teste.

O teste automatizado (`tests/smoke.php`) também deve continuar passando:

```
wp eval-file wp-content/plugins/cedu-inscricoes-campanhas/tests/smoke.php
```
