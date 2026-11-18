/**
 * Gerenciador de inscrições: exclusão em lotes pela REST interna.
 * Dados vindos do PHP em window.icampGerenciador.
 */
( function () {
	'use strict';

	var cfg = window.icampGerenciador;
	var form = document.getElementById( 'icamp-form-inscricoes' );
	if ( ! cfg || ! form ) {
		return;
	}

	var t = cfg.textos;
	var painel = document.getElementById( 'icamp-progresso' );
	var barra = painel.querySelector( '.icamp-barra-preenchida' );
	var status = painel.querySelector( '.icamp-status' );
	var log = painel.querySelector( '.icamp-log' );
	var ocupado = false;

	function formatar( texto ) {
		var args = Array.prototype.slice.call( arguments, 1 );
		var i = 0;
		return texto.replace( /%(\d+\$)?d/g, function ( _, pos ) {
			return String( pos ? args[ parseInt( pos, 10 ) - 1 ] : args[ i++ ] );
		} );
	}

	function chamar( rota, corpo ) {
		return fetch( cfg.rest + rota, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
			body: JSON.stringify( corpo )
		} ).then( function ( r ) {
			return r.json().then( function ( dados ) {
				if ( ! r.ok ) {
					throw new Error( ( dados && dados.message ) || r.status + ' ' + r.statusText );
				}
				return dados;
			} );
		} );
	}

	function travarBotoes( travar ) {
		form.querySelectorAll( 'button, input[type=submit], #icamp-apagar-todas' ).forEach( function ( b ) {
			b.disabled = travar;
		} );
	}

	function iniciar() {
		ocupado = true;
		travarBotoes( true );
		painel.hidden = false;
		painel.classList.remove( 'icamp-ok', 'icamp-erro' );
		log.textContent = '';
		atualizar( 0, 1 );
		status.textContent = t.apagando;
		painel.scrollIntoView( { behavior: 'smooth', block: 'center' } );
	}

	function atualizar( feitas, total ) {
		var pct = total > 0 ? Math.min( 100, Math.round( ( feitas / total ) * 100 ) ) : 100;
		barra.style.width = pct + '%';
		barra.textContent = pct + '%';
		status.textContent = formatar( t.progresso, feitas, total );
	}

	function registrar( linhas ) {
		( linhas || [] ).forEach( function ( linha ) {
			var li = document.createElement( 'li' );
			li.textContent = linha;
			log.appendChild( li );
		} );
		log.scrollTop = log.scrollHeight;
	}

	function concluir( apagadas, midias ) {
		painel.classList.add( 'icamp-ok' );
		barra.style.width = '100%';
		barra.textContent = '100%';
		status.textContent = formatar( t.concluido, apagadas, midias );
		window.setTimeout( function () {
			window.location.reload();
		}, 1800 );
	}

	function falhar( erro ) {
		ocupado = false;
		painel.classList.add( 'icamp-erro' );
		status.textContent = t.erro + ' ' + ( erro && erro.message ? erro.message : erro );
		travarBotoes( false );
	}

	// Apaga uma lista de IDs, em lotes.
	function apagarIds( ids ) {
		var total = ids.length;
		var feitas = 0;
		var apagadas = 0;
		var midias = 0;
		iniciar();

		( function proximo() {
			var lote = ids.splice( 0, cfg.lote );
			if ( ! lote.length ) {
				concluir( apagadas, midias );
				return;
			}
			chamar( 'excluir', { ids: lote } ).then( function ( r ) {
				feitas += r.processados;
				apagadas += r.apagadas;
				midias += r.midias;
				registrar( r.log );
				atualizar( feitas, total );
				proximo();
			} ).catch( falhar );
		} )();
	}

	// Apaga tudo o que atende aos filtros atuais, em lotes.
	function apagarFiltro() {
		var total = cfg.total;
		var apagadas = 0;
		var midias = 0;
		iniciar();

		( function proximo() {
			chamar( 'excluir-filtro', {
				edicao: cfg.filtros.edicao,
				status: cfg.filtros.status,
				busca: cfg.filtros.busca,
				lote: cfg.lote
			} ).then( function ( r ) {
				apagadas += r.apagadas;
				midias += r.midias;
				registrar( r.log );
				atualizar( Math.max( 0, total - r.restantes ), total );
				if ( r.erro ) {
					falhar( r.erro );
				} else if ( r.fim ) {
					concluir( apagadas, midias );
				} else {
					window.setTimeout( proximo, 200 );
				}
			} ).catch( falhar );
		} )();
	}

	// Ação em massa da tabela.
	form.addEventListener( 'submit', function ( e ) {
		var botao = e.submitter;
		if ( ! botao || ( botao.id !== 'doaction' && botao.id !== 'doaction2' ) ) {
			return;
		}
		var select = form.querySelector( botao.id === 'doaction' ? '#bulk-action-selector-top' : '#bulk-action-selector-bottom' );
		if ( ! select || select.value !== 'icamp-excluir' ) {
			return;
		}
		e.preventDefault();
		if ( ocupado ) {
			return;
		}

		var ids = Array.prototype.map.call( form.querySelectorAll( 'input[name="ids[]"]:checked' ), function ( c ) {
			return parseInt( c.value, 10 );
		} );
		if ( ! ids.length ) {
			window.alert( t.nenhumaSelecionada );
			return;
		}
		if ( window.confirm( formatar( t.confirmarSelecionadas, ids.length ) ) ) {
			apagarIds( ids );
		}
	} );

	// "Apagar de vez" de uma linha e "Apagar todas desta lista".
	form.addEventListener( 'click', function ( e ) {
		var alvo = e.target;

		if ( alvo.classList.contains( 'icamp-apagar-uma' ) ) {
			e.preventDefault();
			if ( ! ocupado && window.confirm( formatar( t.confirmarSelecionadas, 1 ) ) ) {
				apagarIds( [ parseInt( alvo.getAttribute( 'data-id' ), 10 ) ] );
			}
			return;
		}

		if ( alvo.id === 'icamp-apagar-todas' ) {
			e.preventDefault();
			if ( ocupado ) {
				return;
			}
			var resposta = window.prompt( formatar( t.confirmarTodas, cfg.total ) );
			if ( resposta === null ) {
				return;
			}
			if ( resposta !== t.fraseConfirmacao ) {
				window.alert( t.fraseIncorreta );
				return;
			}
			apagarFiltro();
		}
	} );
}() );
