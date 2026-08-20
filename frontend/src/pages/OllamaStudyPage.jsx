import { useState, useEffect, useRef } from 'react'
import './OllamaStudyPage.css'

// Mesma origem da pagina: o nginx encaminha /ollama-api/ para o container do Ollama.
// Evita CORS, ponte na 11434 e qualquer diferenca entre WSL e Windows.
const OLLAMA = '/ollama-api'
const MODELO_EMBED = 'nomic-embed-text'
const MODELO_LLM = 'qwen2.5:7b'

const PROMPT_SISTEMA = `Voce analisa chamados de suporte de um sistema para empresas de transporte (SaaS).
Dois chamados sao RELACIONADOS quando descrevem o MESMO problema tecnico - mesma causa e
mesma solucao - ainda que o cliente use palavras completamente diferentes.
Responda SOMENTE JSON: {"relacionado": boolean, "confianca": 0 a 1, "motivo": "frase curta"}`

const EXEMPLOS_FEWSHOT = [
  ['Erro ao emitir nota para o cliente X', 'Erro ao cadastrar o cliente X',
    false, 'Acoes diferentes: emitir documento vs cadastrar cadastro.'],
  ['O sistema esta fora do ar', 'Nao consigo abrir o site de jeito nenhum',
    true, 'Ambos relatam indisponibilidade total de acesso.'],
  ['Nao consigo emitir o CTe', 'Nao consigo emitir o MDFe',
    false, 'Documentos fiscais distintos, com fluxos distintos.'],
  ['Nao recebi a fatura por email', 'A cobranca desse mes nao chegou',
    true, 'Ambos relatam nao recebimento do documento de cobranca.'],
]

const SCHEMA_RESPOSTA = {
  type: 'object',
  properties: {
    relacionado: { type: 'boolean' },
    confianca: { type: 'number' },
    motivo: { type: 'string' },
  },
  required: ['relacionado', 'confianca', 'motivo'],
}

const EXEMPLOS_RAPIDOS = [
  { nome: 'Duplicata real — RPS/NFSe (G1)',
    a: 'bom dia! estamos com porblemas na emissão de RPS NFSE n 66 dando erro na emissão',
    b: 'NAO ESTAMOS CONSEGUINDO EMITIR RPS, COMO PODEMOS RESOLVER?' },
  { nome: 'Duplicata real — ordem carregando (G2)',
    a: 'QUAL SERIA O PROBLEMA NA EMISSÃO DA ORDEM FICA SÓ CARREGANDO NA HORA DE MANIFESTAR VERIFICAR!!!',
    b: 'AO TENTAR GERAR ORDEM FICA SOMENTE CARREGANDO NAO GERA ORDEM DE CARREGAMENTO' },
  { nome: 'Duplicata real — CT-e franquia (G4)',
    a: 'Boa tarde! Estamos com o seguinte erro ao emitir CTE franquia. "OCORREU UM ERRO INTERNO NA BASE DA FRANQUIA" Erro anexado.',
    b: 'Boa tarde, estamos com um erro , Na hora de emitir o cte na franquia , não gera, poderiam estar verificando, por favor' },
  { nome: 'A que o LLM perdeu — CIOT (G5)',
    a: 'Boa tarde, preciso de atendimento... estamos com 4 caminhões parados e não conseguimos emitir os dctos pra liberar os motoristas por favor retornem se possivel ao telefone ao importar os dados do manifesto do cliente, ele não abre a opção de gerar o pedagio',
    b: 'NÃO ESTAMOS CONSEGUINDO GERAR CONTRATO AVULSO, QUANDO É INFORMADO CIOT NÃO ABRE A OPÇÃO DE COLOCAR O PEGADIO. ESTAMOS HA 24 HORAS TENTANDO' },
  { nome: 'Nao e duplicata — RPS x ordem',
    a: 'CAMINHAO PARADO, ESTA COM ERRO NA EMISSAO DE RPS',
    b: 'AO TENTAR GERAR ORDEM FICA SOMENTE CARREGANDO NAO GERA ORDEM DE CARREGAMENTO' },
  { nome: 'Nao e duplicata — dois CT-e distintos',
    a: 'Boa tarde, estamos com um erro , Na hora de emitir o cte na franquia , não gera, poderiam estar verificando, por favor',
    b: 'Foi emitido o CTE com Coleta em SP e destino GO, sendo o pagador de GO também, o cte está puxando CFOP 6932 mas nossa contabilidade disse que o correto seria CFOP 5932. Nº do CTE 344' },
]


// Pares REAIS da base atua (PDF de tickets duplicados, 18/02 a 18/08/2026).
// Cosseno medido com nomic-embed-text nesta maquina.
const PARES_REAIS = [
  { cos: 0.9516, dup: true, rot: 'G6 carga caminhonete' },
  { cos: 0.8854, dup: true, rot: 'G1 RPS/NFSe' },
  { cos: 0.8446, dup: true, rot: 'G4 CTe franquia' },
  { cos: 0.7751, dup: true, rot: 'G1 RPS/NFSe' },
  { cos: 0.7124, dup: true, rot: 'G3 RPS/NFSe' },
  { cos: 0.7120, dup: true, rot: 'G1 RPS/NFSe' },
  { cos: 0.6910, dup: true, rot: 'G5 CIOT/pedagio' },
  { cos: 0.6790, dup: true, rot: 'G2 ordem carregando' },
  { cos: 0.7369, dup: false, rot: 'RPS x ordem carregando' },
  { cos: 0.7024, dup: false, rot: 'RPS x CTe franquia' },
  { cos: 0.6861, dup: false, rot: 'CTe franquia x CFOP' },
  { cos: 0.6737, dup: false, rot: 'CFOP x ANTT' },
  { cos: 0.6627, dup: false, rot: 'CIOT x alterar recebedor' },
  { cos: 0.6609, dup: false, rot: 'caminhonete x filial nova' },
  { cos: 0.6603, dup: false, rot: 'caminhonete x RPS' },
  { cos: 0.6582, dup: false, rot: 'CTe franquia x ordem' },
  { cos: 0.6529, dup: false, rot: 'RPS x CIOT' },
  { cos: 0.6361, dup: false, rot: 'RPS x ANTT' },
  { cos: 0.6333, dup: false, rot: 'ANTT x alterar recebedor' },
  { cos: 0.6281, dup: false, rot: 'ordem x CFOP' },
]

// Medido sobre os 20 pares reais (8 duplicatas confirmadas no PDF).
// "com exemplos" = few-shot: 3 pares ja classificados vao no prompt antes do par real.
const ESTRATEGIAS = [
  { nome: 'Embedding', det: 'limiar 0,67', achou: 8, perdeu: 0, falso: 4, recall: 100, prec: 67, ms: '~60 ms', tipo: 'embed', top: true },
  { nome: 'llama3.1:8b', det: 'com exemplos', achou: 8, perdeu: 0, falso: 4, recall: 100, prec: 67, ms: '~1,2 s', tipo: 'llm' },
  { nome: 'qwen2.5-coder:7b', det: 'com exemplos', achou: 8, perdeu: 0, falso: 3, recall: 100, prec: 73, ms: '~1,2 s', tipo: 'llm', melhorLlm: true },
  { nome: 'qwen2.5:7b', det: 'sem exemplos', achou: 7, perdeu: 1, falso: 1, recall: 88, prec: 88, ms: '~0,8 s', tipo: 'llm' },
  { nome: 'qwen2.5:7b', det: 'com exemplos', achou: 7, perdeu: 1, falso: 2, recall: 88, prec: 78, ms: '~1,2 s', tipo: 'llm' },
  { nome: 'qwen2.5-coder:7b', det: 'sem exemplos', achou: 6, perdeu: 2, falso: 1, recall: 75, prec: 86, ms: '~1,1 s', tipo: 'llm' },
  { nome: 'llama3.1:8b', det: 'sem exemplos', achou: 4, perdeu: 4, falso: 0, recall: 50, prec: 100, ms: '~1,1 s', tipo: 'llm' },
]


const LIMIAR = 0.67

function cosseno(a, b) {
  let dot = 0, na = 0, nb = 0
  for (let i = 0; i < a.length; i++) { dot += a[i] * b[i]; na += a[i] * a[i]; nb += b[i] * b[i] }
  return dot / (Math.sqrt(na) * Math.sqrt(nb))
}

function montarCorpoEmbed(a, b) {
  return { model: MODELO_EMBED, input: [a, b] }
}

function montarCorpoLlm(a, b, comExemplos) {
  const messages = [{ role: 'system', content: PROMPT_SISTEMA }]
  if (comExemplos) {
    for (const [ea, eb, rel, mot] of EXEMPLOS_FEWSHOT) {
      messages.push({ role: 'user', content: `CHAMADO A: ${ea}\nCHAMADO B: ${eb}` })
      messages.push({ role: 'assistant', content: JSON.stringify({ relacionado: rel, confianca: 0.9, motivo: mot }) })
    }
  }
  messages.push({ role: 'user', content: `CHAMADO A: ${a}\nCHAMADO B: ${b}` })
  return {
    model: MODELO_LLM, stream: false, keep_alive: '10m',
    format: SCHEMA_RESPOSTA, options: { temperature: 0, num_predict: 200 }, messages,
  }
}

function EscalaCosseno() {
  const [visivel, setVisivel] = useState(false)
  const ref = useRef(null)
  useEffect(() => {
    const el = ref.current
    if (!el) return
    const io = new IntersectionObserver(
      ([e]) => { if (e.isIntersecting) { setVisivel(true); io.disconnect() } },
      { threshold: 0.35 }
    )
    io.observe(el)
    return () => io.disconnect()
  }, [])

  const MIN = 0.60, MAX = 0.97
  const pos = (c) => ((c - MIN) / (MAX - MIN)) * 100
  const mesmos = PARES_REAIS.filter(p => p.dup)
  const diferentes = PARES_REAIS.filter(p => !p.dup)
  const mesmoMin = Math.min(...mesmos.map(p => p.cos))
  const mesmoMax = Math.max(...mesmos.map(p => p.cos))
  const difMin = Math.min(...diferentes.map(p => p.cos))
  const difMax = Math.max(...diferentes.map(p => p.cos))

  return (
    <div className={`escala ${visivel ? 'escala--ativa' : ''}`} ref={ref}>
      <div className="escala__faixas">
        <div className="escala__faixa escala__faixa--mesmo"
          style={{ left: `${pos(mesmoMin)}%`, width: `${pos(mesmoMax) - pos(mesmoMin)}%` }}>
          <span className="escala__faixaRot">duplicatas confirmadas</span>
        </div>
        <div className="escala__faixa escala__faixa--dif"
          style={{ left: `${pos(difMin)}%`, width: `${pos(difMax) - pos(difMin)}%` }}>
          <span className="escala__faixaRot">tickets distintos</span>
        </div>
      </div>

      <div className="escala__trilho">
        {PARES_REAIS.map((p, i) => (
          <div key={i}
            className={`escala__ponto ${p.dup ? 'escala__ponto--mesmo' : 'escala__ponto--dif'}`}
            style={{ left: `${pos(p.cos)}%`, '--atraso': `${i * 45}ms` }}>
            <div className="escala__tooltip">
              <strong>{p.cos.toFixed(4)}</strong>
              <span>{p.rot}</span>
            </div>
          </div>
        ))}
      </div>

      <div className="escala__limiar" style={{ left: `${pos(0.67)}%` }}>
        <span>limiar 0,67</span>
      </div>

      <div className="escala__eixo">
        {[0.65, 0.70, 0.75, 0.80, 0.85, 0.90, 0.95].map(t => (
          <span key={t} className="escala__tick" style={{ left: `${pos(t)}%` }}>{t.toFixed(2)}</span>
        ))}
      </div>

      <p className="escala__legenda">
        Cada ponto e um par de tickets reais da base. Em ciano, as
        <strong> 8 duplicatas confirmadas</strong>; em vermelho, pares de
        <strong> problemas distintos</strong>. As faixas ainda se tocam — mas em
        <strong> 0,67</strong> nenhuma duplicata fica de fora.
      </p>
    </div>
  )
}

function Json({ dados }) {
  return <pre className="json">{JSON.stringify(dados, null, 2)}</pre>
}

export default function OllamaStudyPage() {
  const [chamadoA, setChamadoA] = useState('bom dia! estamos com porblemas na emissão de RPS NFSE n 66 dando erro na emissão')
  const [chamadoB, setChamadoB] = useState('NAO ESTAMOS CONSEGUINDO EMITIR RPS, COMO PODEMOS RESOLVER?')
  const [comExemplos, setComExemplos] = useState(true)
  const [rodando, setRodando] = useState(null)
  const [embed, setEmbed] = useState(null)
  const [llm, setLlm] = useState(null)
  const [erro, setErro] = useState(null)

  const corpoEmbed = montarCorpoEmbed(chamadoA, chamadoB)
  const corpoLlm = montarCorpoLlm(chamadoA, chamadoB, comExemplos)

  async function rodarEmbed() {
    setRodando('embed'); setErro(null); setEmbed(null)
    const t0 = performance.now()
    try {
      const r = await fetch(`${OLLAMA}/api/embed`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(corpoEmbed),
      })
      if (!r.ok) throw new Error(`HTTP ${r.status}`)
      const d = await r.json()
      const sim = cosseno(d.embeddings[0], d.embeddings[1])
      setEmbed({ sim, dim: d.embeddings[0].length, ms: Math.round(performance.now() - t0),
        loadMs: Math.round((d.load_duration || 0) / 1e6) })
    } catch (e) {
      const rede = e.name === 'TypeError' || e.name === 'TimeoutError' || /fetch/i.test(e.message)
      setErro(rede
        ? 'Nao foi possivel falar com o Ollama. A pagina chama /ollama-api/ no proprio nginx; verifique se os containers estao no ar.'
        : `Falha no /api/embed: ${e.message}`)
    } finally { setRodando(null) }
  }

  async function rodarLlm() {
    setRodando('llm'); setErro(null); setLlm(null)
    const t0 = performance.now()
    try {
      const r = await fetch(`${OLLAMA}/api/chat`, {
        method: 'POST', headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(corpoLlm),
        signal: AbortSignal.timeout(180000),
      })
      if (!r.ok) throw new Error(`HTTP ${r.status}`)
      const d = await r.json()
      setLlm({ ...JSON.parse(d.message.content), ms: Math.round(performance.now() - t0),
        tokensPrompt: d.prompt_eval_count, tokensGerados: d.eval_count,
        loadMs: Math.round((d.load_duration || 0) / 1e6) })
    } catch (e) {
      const rede = e.name === 'TypeError' || e.name === 'TimeoutError' || /fetch/i.test(e.message)
      setErro(rede
        ? 'Nao foi possivel falar com o Ollama. A pagina chama /ollama-api/ no proprio nginx, ' +
          'entao verifique se os containers nginx e ollama estao no ar: docker compose ps'
        : `Falha no /api/chat: ${e.message}`)
    } finally { setRodando(null) }
  }

  async function rodarAmbos() { await rodarEmbed(); await rodarLlm() }

  const embedVeredito = embed ? (embed.sim >= LIMIAR ? 'RELACIONADOS' : 'NAO RELACIONADOS') : null
  const llmVeredito = llm ? (llm.relacionado ? 'RELACIONADOS' : 'NAO RELACIONADOS') : null
  const divergem = embedVeredito && llmVeredito && embedVeredito !== llmVeredito

  return (
    <div className="est">
      <header className="est__topo">
        <div className="est__eyebrow">Comparacao de textos com IA local · embedding vs LLM · base atua</div>
        <h1 className="est__titulo">
          Dois textos falam<br />
          da <em>mesma coisa</em>?
        </h1>
        <p className="est__resumo">
          Comparar dois textos escritos por pessoas diferentes e um problema que aparece em todo
          lugar — e aqui ele custou <strong>248 horas</strong> de atendimento em tickets
          duplicados. Medimos as duas formas de resolve-lo com IA rodando localmente:
          <strong> similaridade por embedding</strong> e <strong>um LLM generativo</strong>.
          Abaixo, o que cada uma faz, quanto custa e onde cada uma erra — tudo contra dados
          reais da base.
        </p>
      </header>

      <section className="est__secao">
        <div className="est__cabecalho">
          <span className="est__num">Antes de tudo</span>
          <h2>Duas maneiras muito diferentes de usar um modelo</h2>
        </div>
        <p className="est__intro">
          As duas abordagens testadas usam inteligencia artificial, mas fazem coisas
          distintas. Vale entender a diferenca antes de olhar os numeros.
        </p>

        <div className="conceitos">
          <article className="conceito conceito--embed">
            <span className="conceito__tag">Embedding</span>
            <h3>Transforma texto em numeros</h3>
            <p>
              O modelo le um texto e devolve uma lista de <strong>768 numeros</strong> — um
              <em> vetor</em>. Esse vetor e uma especie de coordenada: textos com significado
              parecido caem em posicoes proximas. O modelo <strong>nao responde nada</strong>{' '}
              sobre os textos; ele so os converte.
            </p>
            <p className="conceito__nota">
              Modelo usado: <code>nomic-embed-text</code>. E pequeno (274 MB) e rapido porque
              nao precisa escrever texto — so calcular a posicao.
            </p>
          </article>

          <article className="conceito conceito--llm">
            <span className="conceito__tag">Geracao (LLM)</span>
            <h3>Le, raciocina e responde</h3>
            <p>
              LLM e a sigla de <em>Large Language Model</em> — o mesmo tipo de modelo por tras
              do ChatGPT. Aqui ele recebe os dois chamados e uma instrucao em portugues, e
              <strong> devolve um veredito com justificativa</strong>. Entende negacao, contexto
              e detalhes que o vetor nao captura.
            </p>
            <p className="conceito__nota">
              Modelos usados: <code>qwen2.5:7b</code>, <code>qwen2.5-coder:7b</code> e
              <code> llama3.1:8b</code>. Sao ~5 GB cada, e precisam gerar palavra por palavra —
              por isso demoram mais.
            </p>
          </article>
        </div>

        <div className="cosseno">
          <h3>Como a comparacao acontece — e onde ela acontece</h3>
          <p>
            Este e o ponto que costuma passar despercebido: no embedding, o Ollama
            <strong> nao diz se os textos sao parecidos</strong>. Ele devolve dois vetores, e a
            comparacao e feita depois, por conta de quem chamou.
          </p>

          <ol className="cosseno__passos">
            <li>
              <span className="cosseno__n">1</span>
              <div>
                <strong>Os dois chamados viram vetores</strong>
                <p>Uma chamada ao <code>/api/embed</code> devolve duas listas de 768 numeros.</p>
              </div>
            </li>
            <li>
              <span className="cosseno__n">2</span>
              <div>
                <strong>Calcula-se a similaridade de cosseno</strong>
                <p>
                  Mede o quanto os dois vetores apontam para a mesma direcao. O resultado vai de
                  0 a 1: quanto mais perto de 1, mais parecidos. E uma conta simples —
                  multiplicar os numeros par a par e somar.
                </p>
              </div>
            </li>
            <li>
              <span className="cosseno__n">3</span>
              <div>
                <strong>Compara-se com um limiar</strong>
                <p>
                  Acima do valor de corte, considera-se duplicata. Esse numero nao vem do modelo:
                  e escolhido a partir de dados ja classificados — foi assim que chegamos a 0,67.
                </p>
              </div>
            </li>
          </ol>

          <p className="cosseno__codigo">
            Nesta pagina, os passos 2 e 3 rodam <strong>no seu navegador</strong>, em poucas
            linhas de JavaScript. Nenhum servidor participa da comparacao.
          </p>
        </div>
      </section>

      <section className="est__secao est__secao--escala">
        <div className="est__cabecalho">
          <span className="est__num">A descoberta</span>
          <h2>Nos dados reais, o embedding acha todas as duplicatas</h2>
        </div>
        <EscalaCosseno />
        <div className="recall">
          <div className="recall__item recall__item--bom">
            <div className="recall__valor">8/8</div>
            <div className="recall__texto">
              <strong>duplicatas encontradas</strong>
              <span>Com limiar 0,67, nenhuma passa despercebida.</span>
            </div>
          </div>
          <div className="recall__contra">ao custo de</div>
          <div className="recall__item">
            <div className="recall__valor recall__valor--neutro">4</div>
            <div className="recall__texto">
              <strong>falsos positivos</strong>
              <span>O atendente descarta em segundos.</span>
            </div>
          </div>
        </div>

        <p className="est__nota">
          <strong>Por que ainda ha 4 falsos positivos.</strong> O par distinto de maior
          pontuacao (0,7369) fica acima da duplicata mais fraca (0,6790) — as faixas se tocam.
          Subir o corte para 0,70 elimina 2 falsos positivos, mas <strong>perde 2
          duplicatas</strong>. Para este caso, essa troca nao compensa.
        </p>


      </section>

      <section className="est__secao">
        <div className="est__cabecalho">
          <span className="est__num">Teste voce mesmo</span>
          <h2>Dois chamados, as duas abordagens</h2>
        </div>

        <div className="exemplos">
          {EXEMPLOS_RAPIDOS.map(ex => (
            <button key={ex.nome} className="exemplos__btn"
              onClick={() => { setChamadoA(ex.a); setChamadoB(ex.b); setEmbed(null); setLlm(null) }}>
              {ex.nome}
            </button>
          ))}
        </div>

        <div className="entrada">
          <label className="entrada__campo">
            <span className="entrada__rot">Chamado A</span>
            <textarea value={chamadoA} onChange={e => setChamadoA(e.target.value)} rows={2} />
          </label>
          <label className="entrada__campo">
            <span className="entrada__rot">Chamado B</span>
            <textarea value={chamadoB} onChange={e => setChamadoB(e.target.value)} rows={2} />
          </label>
        </div>

        <div className="acoes">
          <button className="btn btn--principal" onClick={rodarAmbos} disabled={!!rodando}>
            {rodando === 'llm' ? 'Consultando o LLM (a 1a chamada carrega o modelo, ~7s)...' : rodando ? 'Calculando...' : 'Comparar com as duas abordagens'}
          </button>
          <button className="btn" onClick={rodarEmbed} disabled={!!rodando}>So embedding</button>
          <button className="btn" onClick={rodarLlm} disabled={!!rodando}>So LLM</button>
          <label className="alternar">
            <input type="checkbox" checked={comExemplos}
              onChange={e => { setComExemplos(e.target.checked); setLlm(null) }} />
            <span>Incluir os 4 exemplos no prompt <em>(few-shot)</em></span>
          </label>
        </div>

        {erro && <div className="erro">{erro}</div>}

        {divergem && (
          <div className="divergencia">
            As duas abordagens <strong>divergem</strong> neste par — e o caso que o estudo isola.
          </div>
        )}

        <div className="resultados">
          <article className={`res res--embed ${embed ? 'res--cheio' : ''}`}>
            <header className="res__topo">
              <span className="res__tag">Embedding</span>
              <code className="res__ep">POST /api/embed</code>
            </header>
            {embed ? (
              <>
                <div className="res__numero">{embed.sim.toFixed(4)}</div>
                <div className={`res__veredito ${divergem ? 'res__veredito--erro' : embed.sim >= LIMIAR ? 'res__veredito--sim' : 'res__veredito--nao'}`}>
                  {embedVeredito}{divergem && <em> — discorda do LLM</em>}
                </div>
                <dl className="res__meta">
                  <div><dt>Limiar</dt><dd>{LIMIAR}</dd></div>
                  <div><dt>Dimensoes</dt><dd>{embed.dim}</dd></div>
                  <div><dt>Tempo</dt><dd>{embed.ms} ms</dd></div>
                </dl>
                <p className="res__aviso">
                  O cosseno e calculado no navegador: o Ollama devolve dois vetores, nao um veredito.
                </p>
              </>
            ) : (
              <p className="res__vazio">Devolve dois vetores de 768 numeros. A comparacao e sua.</p>
            )}
          </article>

          <article className={`res res--llm ${llm ? 'res--cheio' : ''}`}>
            <header className="res__topo">
              <span className="res__tag">Geracao (LLM)</span>
              <code className="res__ep">POST /api/chat</code>
            </header>
            {llm ? (
              <>
                <div className="res__numero">{llm.confianca}</div>
                <div className={`res__veredito ${llm.relacionado ? 'res__veredito--sim' : 'res__veredito--nao'}`}>
                  {llmVeredito}
                </div>
                <p className="res__motivo">&ldquo;{llm.motivo}&rdquo;</p>
                <dl className="res__meta">
                  <div><dt>Tokens no prompt</dt><dd>{llm.tokensPrompt}</dd></div>
                  <div><dt>Gerados</dt><dd>{llm.tokensGerados}</dd></div>
                  <div><dt>Tempo</dt><dd>{llm.ms} ms</dd></div>
                </dl>
                {llm.loadMs > 500 && (
                  <p className="res__aviso">
                    {llm.loadMs} ms so para carregar o modelo na memoria. Chamadas seguidas sao muito mais rapidas.
                  </p>
                )}
              </>
            ) : (
              <p className="res__vazio">Devolve um veredito com justificativa, ja em JSON estruturado.</p>
            )}
          </article>
        </div>
      </section>

      <section className="est__secao">
        <div className="est__cabecalho">
          <span className="est__num">O que sai daqui</span>
          <h2>Exatamente o que vai no corpo da requisicao</h2>
        </div>
        <p className="est__intro">
          Sem API intermediaria e sem backend proprio: a pagina fala com o Ollama pelo <code>/ollama-api/</code> do nginx.
          Os corpos abaixo acompanham os campos acima em tempo real.
        </p>
        <div className="corpos">
          <div className="corpo">
            <header className="corpo__topo">
              <code>POST /ollama-api/api/embed</code>
              <span className="corpo__tag corpo__tag--embed">embedding</span>
            </header>
            <Json dados={corpoEmbed} />
            <p className="corpo__nota">
              <code>input</code> aceita um array — dois textos numa chamada so. A resposta traz
              <code> embeddings</code>: dois vetores de 768 numeros, ja normalizados.
            </p>
          </div>
          <div className="corpo">
            <header className="corpo__topo">
              <code>POST /ollama-api/api/chat</code>
              <span className="corpo__tag corpo__tag--llm">geracao</span>
            </header>
            <Json dados={corpoLlm} />
            <p className="corpo__nota">
              <code>format</code> com JSON Schema obriga o modelo a responder no formato — ele nao
              consegue devolver texto solto. <code>temperature: 0</code> torna o resultado
              reproduzivel. {comExemplos
                ? 'Os quatro pares de exemplo entram como turnos user/assistant antes do par real.'
                : 'Sem os exemplos, o modelo tem so a regra escrita no system.'}
            </p>
          </div>
        </div>
      </section>

      <section className="est__secao">
        <div className="est__cabecalho">
          <span className="est__num">A decisao</span>
          <h2>Perder uma duplicata custa mais que errar um palpite</h2>
        </div>
        <p className="est__intro">
          As duas metricas nao valem o mesmo aqui. Uma duplicata <strong>nao detectada</strong>
          vira um segundo atendimento inteiro — foi assim que 834 tickets viraram 248 horas.
          Um <strong>falso positivo</strong> custa o atendente descartar uma sugestao. Por isso
          a coluna que decide e <em>recall</em>, nao precisao.
        </p>

        <table className="placar placar--estrat">
          <thead>
            <tr>
              <th>Abordagem</th><th>Achou</th><th>Perdeu</th>
              <th>Falso+</th><th>Recall</th><th>Precisao</th><th>Tempo</th>
            </tr>
          </thead>
          <tbody>
            {ESTRATEGIAS.map((e, i) => (
              <tr key={i} className={e.top ? 'placar__linha--vencedor' : (e.melhorLlm ? 'placar__linha--destaque' : (e.perdeu > 2 ? 'placar__linha--falha' : ''))}>
                <td>
                  <strong>{e.nome}</strong>
                  <span>{e.tipo === 'embed' ? 'similaridade de cosseno' : 'LLM · '}{e.tipo === 'llm' ? e.det : ` · ${e.det}`}</span>
                </td>
                <td className="placar__num">{e.achou}/8</td>
                <td className="placar__num">{e.perdeu}</td>
                <td className="placar__num">{e.falso}</td>
                <td className="placar__num">{e.recall}%</td>
                <td className="placar__num">{e.prec}%</td>
                <td className="placar__num">{e.ms}</td>
              </tr>
            ))}
          </tbody>
        </table>

        <div className="glossario">
          <div>
            <strong>Recall</strong>
            <span>Das 8 duplicatas reais, quantas o metodo encontrou. E a metrica que importa aqui.</span>
          </div>
          <div>
            <strong>Precisao</strong>
            <span>Dos pares que apontou como duplicata, quantos realmente eram.</span>
          </div>
          <div>
            <strong>Com exemplos (few-shot)</strong>
            <span>Tres pares ja classificados vao dentro do prompt, antes do par real. O modelo aprende o criterio pelo exemplo, sem nenhum treinamento.</span>
          </div>
        </div>

        <div className="porque">
          <h3>Como ler esta tabela</h3>
          <ul>
            <li>
              <strong>Tres abordagens empatam em recall 100%.</strong> Embedding em 0,67,
              <code> llama3.1:8b</code> e <code>qwen2.5-coder:7b</code> — as duas ultimas apenas
              <em> com exemplos no prompt</em>. Nenhuma delas deixa duplicata passar.
            </li>
            <li>
              <strong>Os exemplos no prompt decidem o resultado do LLM.</strong> O
              <code> llama3.1:8b</code> sai de <strong>4/8 para 8/8</strong> so por receber tres
              pares ja classificados. Sem eles, perde metade das duplicatas. E a mudanca mais
              barata que existe: nao ha treinamento, so texto a mais na requisicao.
            </li>
            <li>
              <strong>No empate, o embedding vence pelo custo.</strong> ~60 ms contra ~1,2 s,
              sem ocupar GPU e com resultado sempre igual para a mesma entrada. Roda a janela
              de 3 dias inteira sem virar gargalo.
            </li>
            <li>
              <strong>O melhor LLM tem a melhor precisao entre os que acham tudo.</strong>
              <code> qwen2.5-coder:7b</code> com exemplos erra 3 palpites contra 4 do embedding —
              vantagem pequena, que custa 20x mais tempo.
            </li>
          </ul>
        </div>

        <p className="est__nota">
          <strong>A duplicata que o LLM perdeu (G5).</strong> O ticket A enterra o sintoma na
          ultima linha, depois de telefone e pedido de urgencia: <em>“...nao abre a opcao de
          gerar o pedagio”</em>. O modelo respondeu <em>“problemas distintos: um relata
          problema com DCTF”</em> — alucinou “DCTF” a partir de “dctos”. O embedding
          pegou o par (0,691).
        </p>
      </section>

      <section className="est__secao">
        <div className="est__cabecalho">
          <span className="est__num">Se um dia precisar</span>
          <h2>Os tres caminhos de treinamento</h2>
        </div>
        <div className="trilhas">
          <article className="trilha trilha--agora">
            <div className="trilha__selo">Ja em uso</div>
            <h3>Exemplos no prompt</h3>
            <p className="trilha__custo">Custo zero · minutos</p>
            <p>
              Quatro pares rotulados dentro da requisicao. Nao ha treino: o modelo aprende o
              criterio lendo os exemplos. Foi o que levou a precisao a 19/19.
            </p>
            <p className="trilha__quando">Comece sempre por aqui. Se resolver, nao ha o que treinar.</p>
          </article>
          <article className="trilha">
            <div className="trilha__selo">Se o few-shot nao bastar</div>
            <h3>Ajustar o modelo de embedding</h3>
            <p className="trilha__custo">~500 pares rotulados · algumas horas · cabe na GPU atual</p>
            <p>
              Ensina o <code>nomic-embed-text</code> que <em>emitir</em> e <em>cadastrar</em> sao
              coisas diferentes neste dominio. E o unico caminho que conserta a falha da secao 1.
            </p>
            <p className="trilha__quando">
              Vale quando o volume crescer: devolve a comparacao em milissegundos no lugar de ~1,2 s.
            </p>
          </article>
          <article className="trilha trilha--cara">
            <div className="trilha__selo">Provavelmente desnecessario</div>
            <h3>Fine-tuning do LLM de 7B</h3>
            <p className="trilha__custo">~1000+ exemplos · dias · QLoRA 4-bit no limite dos 8 GB</p>
            <p>
              Especializa o proprio modelo de geracao. Dificil de justificar quando o modelo base,
              sem treino nenhum, ja acerta 19 de 19.
            </p>
            <p className="trilha__quando">So se o few-shot travar num teto e houver dados de sobra.</p>
          </article>
        </div>
        <p className="est__nota">
          O Ollama <strong>nao treina</strong> — ele so executa. Qualquer um dos dois ultimos
          caminhos exige uma stack Python separada (PyTorch, sentence-transformers ou unsloth) e
          depois converter o resultado para GGUF para servir de volta pelo Ollama.
        </p>
      </section>

      <section className="est__secao">
        <div className="est__cabecalho">
          <span className="est__num">Ideias futuras</span>
          <h2>O mesmo modelo resolve outras validacoes</h2>
        </div>
        <p className="est__intro">
          Detectar duplicata usa so uma fracao do que o modelo ja instalado sabe fazer. As
          ideias abaixo aproveitam a mesma infraestrutura — nenhum servico novo, nenhum
          treinamento. Todas foram testadas nesta maquina com <code>qwen2.5:7b</code>; os
          numeros sao medidos, mas em poucos exemplos.
        </p>

        <article className="ideia ideia--principal">
          <header className="ideia__topo">
            <div>
              <span className="ideia__selo">testado · a mais promissora</span>
              <h3>Chamado tem o minimo para ser atendido?</h3>
            </div>
            <span className="ideia__tempo">~1 s</span>
          </header>
          <p>
            Antes de entrar na fila, o modelo verifica se o atendente conseguiria comecar a
            investigar. Se falta o basico, o proprio sistema ja devolve a pergunta ao cliente —
            em vez de gastar um atendimento so para pedir informacao.
          </p>

          <div className="ideia__demo">
            <div className="demo__lado">
              <span className="demo__rot demo__rot--ruim">Insuficiente</span>
              <p className="demo__ticket">
                “Boa tarde, estamos com um erro, na hora de emitir o cte na franquia, nao gera,
                poderiam estar verificando, por favor”
              </p>
              <div className="demo__saida">
                <span className="demo__campo">faltando</span>
                <code>["identificador"]</code>
                <span className="demo__campo">mensagem gerada</span>
                <p className="demo__msg">
                  “Poderia nos informar o CNPJ ou numero do documento relacionado ao erro?”
                </p>
              </div>
            </div>
            <div className="demo__lado">
              <span className="demo__rot demo__rot--bom">Suficiente</span>
              <p className="demo__ticket">
                “Estamos com o seguinte erro ao emitir CTE franquia. <em>OCORREU UM ERRO INTERNO
                NA BASE DA FRANQUIA</em>. Erro anexado.”
              </p>
              <div className="demo__saida">
                <span className="demo__campo">faltando</span>
                <code>[]</code>
                <span className="demo__campo">acao</span>
                <p className="demo__msg">Segue direto para a fila.</p>
              </div>
            </div>
          </div>

          <p className="ideia__nota">
            <strong>6 de 6 acertos</strong> nos exemplos testados — os tres vagos barrados, os
            tres completos aprovados. Vale registrar como cheguei ai: a primeira versao do prompt
            listava tudo que <em>poderia</em> faltar (numero, print, tela, data) e o modelo tratou
            como checklist, reprovando ate os chamados bons. Trocar por um criterio pratico —
            <em> “um identificador OU o erro exato OU um anexo ja basta”</em> — resolveu. O
            criterio precisa refletir o que o atendente realmente precisa, nao o ideal.
          </p>
        </article>

        <div className="ideias">
          <article className="ideia">
            <header className="ideia__topo">
              <div><span className="ideia__selo">testado</span><h3>Roteamento automatico</h3></div>
              <span className="ideia__tempo">~0,4 s</span>
            </header>
            <p>
              Classifica modulo (fiscal, expedicao, cadastro, financeiro, rastreamento, acesso) e
              tipo (erro, duvida, solicitacao) para mandar direto a fila certa.
            </p>
            <p className="ideia__nota">
              Acertou os 4 exemplos. Como quase tudo caiu em <em>fiscal</em>, a lista de modulos
              precisa refletir a divisao real das suas filas para valer alguma coisa.
            </p>
          </article>

          <article className="ideia">
            <header className="ideia__topo">
              <div><span className="ideia__selo">testado</span><h3>Urgencia pelo texto</h3></div>
              <span className="ideia__tempo">~0,4 s</span>
            </header>
            <p>
              Detecta operacao parada — <em>veiculo parado</em>, <em>motorista esperando</em>,
              <em> carga retida</em> — e prioriza. Devolve tambem o trecho que justificou.
            </p>
            <p className="ideia__nota">
              Marcou como critico o chamado com <em>“4 caminhoes parados”</em> e como normal uma
              duvida de CFOP. Devolver o trecho torna a decisao auditavel.
            </p>
          </article>

          <article className="ideia ideia--ideia">
            <header className="ideia__topo">
              <div><span className="ideia__selo ideia__selo--nao">nao testado</span><h3>Sugerir a resposta</h3></div>
            </header>
            <p>
              Busca chamados antigos parecidos — o mesmo embedding desta pagina — e monta um
              rascunho a partir de como aqueles foram resolvidos. O atendente revisa e envia.
            </p>
            <p className="ideia__nota">
              Exige o historico de respostas, nao so os textos de abertura.
            </p>
          </article>

          <article className="ideia ideia--ideia">
            <header className="ideia__topo">
              <div><span className="ideia__selo ideia__selo--nao">nao testado</span><h3>Agrupar por causa raiz</h3></div>
            </header>
            <p>
              Junta chamados de <strong>clientes diferentes</strong> com o mesmo sintoma na mesma
              janela. Cinco transportadoras relatando erro de RPS na mesma manha e incidente, nao
              coincidencia.
            </p>
            <p className="ideia__nota">
              Extensao natural do que ja foi medido aqui — muda so o recorte, de mesmo cliente
              para qualquer cliente.
            </p>
          </article>

          <article className="ideia ideia--ideia">
            <header className="ideia__topo">
              <div><span className="ideia__selo ideia__selo--nao">nao testado</span><h3>Resumir a conversa</h3></div>
            </header>
            <p>
              Condensa uma thread longa em tres linhas: o que o cliente pediu, o que ja foi
              tentado, o que falta. Util no repasse de turno ou na escalada.
            </p>
            <p className="ideia__nota">
              O modelo aguenta 32 mil tokens de contexto — cabe uma thread inteira.
            </p>
          </article>

          <article className="ideia ideia--ideia">
            <header className="ideia__topo">
              <div><span className="ideia__selo ideia__selo--nao">nao testado</span><h3>Detectar cliente insatisfeito</h3></div>
            </header>
            <p>
              Sinaliza reabertura, cobranca de retorno ou tom de frustacao — como o
              <em> “estamos ha 24 horas tentando”</em> que aparece na base — antes de virar
              reclamacao formal.
            </p>
            <p className="ideia__nota">
              Precisa de cuidado: sinalizar demais gera ruido e o alerta perde valor.
            </p>
          </article>
        </div>

        <p className="est__nota est__nota--ressalva">
          <strong>Como avaliar qualquer uma dessas.</strong> O metodo e o mesmo que usamos aqui:
          separe de 20 a 50 casos reais, classifique voce mesmo <em>antes</em> de ver a resposta do
          modelo, e so entao meça. Numeros de 4 a 6 exemplos, como os desta secao, servem para
          decidir o que vale investigar — nao para colocar em producao.
        </p>

        <p className="est__nota">
          <strong>Duas ficam melhores sem LLM.</strong> Roteamento e urgencia podem ser resolvidos
          por palavra-chave em muitos casos (<em>“caminhao parado”</em>, <em>“CT-e”</em>), com
          custo zero e resultado auditavel. Vale comparar antes de assumir que precisa de modelo —
          o LLM ganha quando o cliente escreve de um jeito que a lista de palavras nao previu.
        </p>
      </section>

      <section className="est__secao est__secao--fecho">
        <div className="est__cabecalho">
          <span className="est__num">Para levar adiante</span>
          <h2>O que isso significa na pratica</h2>
        </div>
        <ol className="conclusoes">
          <li>
            <h3>O metodo mais barato e o mais seguro para este caso</h3>
            <p>
              Embedding com limiar 0,67 achou <strong>todas as 8 duplicatas</strong>, a ~60 ms
              por par, sem ocupar GPU. O LLM tem precisao melhor, mas perde uma duplicata — e
              é justamente perder que custa caro. Nao ha motivo para comecar pelo caminho caro.
            </p>
          </li>
          <li>
            <h3>Dados reais mudaram a conclusao do estudo</h3>
            <p>
              Com pares que eu escrevi, o embedding falhava por completo. Com tickets reais da
              base, acerta tudo. O conjunto sintetico era dificil demais e apontava para a
              decisao errada — nenhum teste com dado inventado deveria ter virado recomendacao.
            </p>
          </li>
          <li>
            <h3>A camada semantica cobre o que o pg_trgm nao ve</h3>
            <p>
              Os grupos 1 a 5 tem similaridade lexical de 0,18 a 0,38: nenhum filtro por texto
              os encontra. Sao 81 tickets no periodo, 33 h de atendimento. E exatamente a faixa
              onde o embedding trabalha bem.
            </p>
          </li>
          <li>
            <h3>O que falta para calibrar de verdade</h3>
            <p>
              8 duplicatas confirmadas nao fixam um limiar, e os pares negativos fui eu que
              montei. O que falta e um export dos <strong>candidatos que a janela de 3 dias
              gera e que nao sao duplicados</strong> — esses so existem na base. Com eles, o
              0,67 sai calibrado contra o caso real.
            </p>
          </li>
        </ol>
        <div className="ambiente">
          <h3>Onde isso foi medido</h3>
          <dl>
            <div><dt>Embedding</dt><dd>nomic-embed-text · 768 dim</dd></div>
            <div><dt>Geracao</dt><dd>qwen2.5-coder:7b</dd></div>
            <div><dt>GPU</dt><dd>RTX 4060 Ti · 8 GB</dd></div>
            <div><dt>Runtime</dt><dd>Ollama 0.5.7 em Docker</dd></div>
          </dl>
          <p>
            Os numeros de duplicatas vem de 20 pares montados a partir do PDF de tickets
            duplicados da base atua (18/02 a 18/08/2026, 41.499 tickets analisados), com as 8
            duplicatas confirmadas nos grupos 1 a 6. Todos os cossenos e vereditos foram medidos
            nesta maquina e sao reproduziveis pela collection do Postman{' '}
            <code>ollama-relatedness</code>.
          </p>
        </div>
        <div className="plataformas">
          <h3>Outras plataformas que valem um teste</h3>
          <p className="plataformas__intro">
            O Ollama e otimo para experimentar: instala rapido e roda em qualquer maquina. Mas
            ele foi feito para uso individual, nao para atender muitas requisicoes ao mesmo
            tempo. Se isso virar producao, estas sao as alternativas — todas rodam os
            <strong> mesmos modelos</strong>, o que muda e a eficiencia.
          </p>

          <div className="plat__grid">
            <article className="plat">
              <header><h4>vLLM</h4><span className="plat__origem">EUA · Berkeley</span></header>
              <p>
                O padrao para servir modelos em producao. Atende dezenas de requisicoes
                simultaneas com a tecnica de <em>continuous batching</em>: agrupa varios pedidos
                numa passagem so pela GPU.
              </p>
              <p className="plat__quando">Quando o volume crescer e a fila comecar a pesar.</p>
            </article>

            <article className="plat">
              <header><h4>SGLang</h4><span className="plat__origem">EUA · aberto</span></header>
              <p>
                Parecido com o vLLM, mas mais rapido quando muitas requisicoes
                <strong> repetem o mesmo inicio de prompt</strong> — exatamente o nosso caso, ja
                que a instrucao e os exemplos sao identicos em toda chamada. Ele reaproveita esse
                trecho em vez de recalcular.
              </p>
              <p className="plat__quando">O candidato mais promissor para a deteccao de duplicatas.</p>
            </article>

            <article className="plat">
              <header><h4>LMDeploy</h4><span className="plat__origem">China · Shanghai AI Lab</span></header>
              <p>
                Do mesmo laboratorio dos modelos InternLM. Costuma liderar os testes de
                velocidade em GPU unica e tem compressao de modelo mais agressiva — util em
                placa de 8 GB como a que usamos aqui.
              </p>
              <p className="plat__quando">Se a GPU atual virar o limite antes do volume.</p>
            </article>

            <article className="plat">
              <header><h4>llama.cpp</h4><span className="plat__origem">aberto · base do Ollama</span></header>
              <p>
                O motor que o proprio Ollama usa por baixo. Usar direto tira a camada de
                conveniencia e da controle fino sobre memoria e quantizacao — inclusive rodando
                so em CPU, sem GPU nenhuma.
              </p>
              <p className="plat__quando">Se precisar rodar em servidor sem placa de video.</p>
            </article>

            <article className="plat">
              <header><h4>Text Embeddings Inference</h4><span className="plat__origem">Hugging Face</span></header>
              <p>
                Especializado <strong>so em embeddings</strong> — nao gera texto. Como a
                abordagem recomendada aqui e justamente o embedding, ele resolve o caso inteiro
                com um servico bem menor que o Ollama.
              </p>
              <p className="plat__quando">Se a decisao final for embedding puro.</p>
            </article>

            <article className="plat plat--api">
              <header><h4>APIs pagas</h4><span className="plat__origem">OpenAI, Anthropic, Google</span></header>
              <p>
                Modelos maiores e mais precisos, sem infraestrutura para manter. O contraponto e
                que <strong>o texto do chamado sai da sua rede</strong> — e chamados de suporte
                trazem CNPJ, telefone e nome de cliente.
              </p>
              <p className="plat__quando">So com aval juridico sobre dados de cliente.</p>
            </article>
          </div>

          <p className="plataformas__nota">
            <strong>Nada disso e urgente.</strong> Trocar de plataforma muda a velocidade e
            quantas requisicoes cabem ao mesmo tempo — <em>nao muda a precisao</em>, que depende
            do modelo e do prompt. Com o volume atual, o Ollama da conta. Vale medir de novo se a
            deteccao passar a rodar sobre a base inteira, e nao sobre uma janela de 3 dias.
          </p>
        </div>

      </section>
    </div>
  )
}
