// The topology as a real diagram, organized around the study's thesis — the *seam*. The
// synchronous web world (nginx, frontend, api, realtime) sits above; the async compute world
// (worker) sits below; Redis is drawn on the boundary between them, the one queue crossing.
// Nodes are colored by runtime so "two Go services, different reasons" reads at a glance.
// Uses the app's tokens via CSS classes, so it inverts cleanly in light mode.
export default function Topology() {
  return (
    <figure className="topo">
      <figcaption className="topo-cap">
        <span>The topology</span>
        <span className="topo-cap-sub">how the pieces actually sit — and where the seam runs</span>
      </figcaption>

      <div className="topo-frame">
        <svg viewBox="0 0 760 520" role="img" aria-label="Service topology: a synchronous web zone over an asynchronous compute zone, split by the Redis queue" className="topo-svg">
          {/* zone backdrops */}
          <rect className="topo-zone topo-zone-web" x="16" y="86" width="728" height="196" rx="10" />
          <rect className="topo-zone topo-zone-compute" x="16" y="330" width="728" height="70" rx="10" />
          <rect className="topo-zone topo-zone-data" x="16" y="424" width="728" height="80" rx="10" />

          <text className="topo-zonelabel" x="30" y="106">SYNCHRONOUS · WEB</text>
          <text className="topo-zonelabel" x="30" y="350">ASYNC · COMPUTE</text>
          <text className="topo-zonelabel" x="30" y="444">SHARED DATA</text>

          {/* ---- edges (drawn first, under the nodes) ---- */}
          <g className="topo-edges">
            {/* host -> nginx */}
            <line x1="380" y1="34" x2="380" y2="52" />
            {/* nginx -> frontend / api / realtime */}
            <path d="M340 78 C 240 78, 150 96, 150 132" />
            <path d="M380 78 L 380 132" />
            <path d="M420 78 C 560 78, 610 96, 610 132" />

            {/* api -> postgres (pdo) */}
            <path className="topo-edge-data" d="M360 184 C 320 300, 300 400, 340 452" />
            {/* realtime -> postgres (pdo) + token check */}
            <path className="topo-edge-data" d="M610 184 C 610 320, 470 410, 420 452" />
          </g>

          {/* ---- the seam: api -> redis -> worker ---- */}
          <g className="topo-seam">
            <path className="topo-edge-queue" d="M320 184 C 220 220, 150 250, 150 300" />
            <path className="topo-edge-queue" d="M150 330 C 150 360, 280 375, 330 380" />
            <text className="topo-queuelabel" x="210" y="238">rpush ▸</text>
            <text className="topo-queuelabel" x="215" y="360">◂ BLPOP</text>
          </g>

          {/* worker -> minio / meilisearch */}
          <g className="topo-edges">
            <path className="topo-edge-data" d="M360 400 C 300 414, 240 424, 210 452" />
            <path className="topo-edge-data" d="M470 400 C 560 414, 600 424, 620 452" />
          </g>

          {/* the boundary line — where sync meets async */}
          <line className="topo-boundary" x1="16" y1="306" x2="744" y2="306" />
          <text className="topo-boundary-label" x="744" y="300" textAnchor="end">the seam</text>

          {/* ---- nodes ---- */}
          {/* host */}
          <g className="topo-node topo-node-host">
            <rect x="330" y="14" width="100" height="24" rx="5" />
            <text x="380" y="30">host:8080</text>
          </g>

          {/* nginx */}
          <g className="topo-node topo-node-infra">
            <rect x="320" y="52" width="120" height="30" rx="6" />
            <text x="380" y="71">nginx</text>
          </g>

          {/* frontend (React) */}
          <g className="topo-node topo-node-react">
            <rect x="70" y="132" width="160" height="52" rx="7" />
            <text className="topo-name" x="150" y="156">frontend</text>
            <text className="topo-role" x="150" y="172">React · Vite</text>
          </g>

          {/* api (Laravel) */}
          <g className="topo-node topo-node-php">
            <rect x="300" y="132" width="160" height="52" rx="7" />
            <text className="topo-name" x="380" y="156">api</text>
            <text className="topo-role" x="380" y="172">Laravel · CRUD</text>
          </g>

          {/* realtime (Go) */}
          <g className="topo-node topo-node-go">
            <rect x="530" y="132" width="160" height="52" rx="7" />
            <text className="topo-name" x="610" y="156">realtime</text>
            <text className="topo-role" x="610" y="172">Go · websockets</text>
          </g>

          {/* redis — on the boundary */}
          <g className="topo-node topo-node-seamnode">
            <rect x="90" y="300" width="120" height="30" rx="6" />
            <text x="150" y="319">redis · queue</text>
          </g>

          {/* worker (Go) */}
          <g className="topo-node topo-node-go">
            <rect x="330" y="360" width="160" height="40" rx="7" />
            <text className="topo-name" x="410" y="379">worker</text>
            <text className="topo-role" x="410" y="393">Go · demo parser</text>
          </g>

          {/* data stores */}
          <g className="topo-node topo-node-data">
            <rect x="300" y="452" width="130" height="34" rx="6" />
            <text x="365" y="473">postgres</text>
          </g>
          <g className="topo-node topo-node-data">
            <rect x="150" y="452" width="120" height="34" rx="6" />
            <text x="210" y="473">minio</text>
          </g>
          <g className="topo-node topo-node-data">
            <rect x="560" y="452" width="130" height="34" rx="6" />
            <text x="625" y="473">meilisearch</text>
          </g>
        </svg>
      </div>

      <div className="topo-legend">
        <span className="topo-key topo-key-php">Laravel · PHP</span>
        <span className="topo-key topo-key-go">Go</span>
        <span className="topo-key topo-key-react">React</span>
        <span className="topo-key topo-key-infra">infra</span>
        <span className="topo-legend-note">the dashed edge is the queue — the only path between the two worlds</span>
      </div>
    </figure>
  )
}
