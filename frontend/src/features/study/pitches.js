// The presenter's companion to the study ledger (tradeoffs.js). Each entry is the same
// architectural decision said OUT LOUD — first person, plain English, the way you'd
// explain it to a person across a table, not a spec. One per topic, keyed by the same
// id, so the deck and the ledger stay in lockstep. No jargon that isn't immediately
// unpacked; every paragraph ends on why the choice mattered.

export const PITCHES = {
  seam:
    "The whole project is really a study of one question: when does a piece of the system deserve to be its own service? My rule is that a boundary has to be earned — a workload has to differ in a way that a separate process actually fixes, like being slow, or long-lived, or heavy. Just being \"a different part of the app\" isn't enough; that's only a reason to make a new folder, not a new service. Keeping that bar high is what stops the project from drowning in plumbing.",

  worker:
    "Parsing a match demo is slow and CPU-heavy — it chews through a big file for seconds or minutes. The rest of the app is ordinary create-read-update-delete stuff. Those are two different kinds of work, so I split them: a Go worker does the heavy parsing, and a Laravel app does the everyday web stuff. Go is good at fast, parallel number-crunching; Laravel is good at building web features quickly. Each service gets to be good at its own job instead of one language compromising on both.",

  realtime:
    "The tactics board is something several people edit at the same time — everyone drags pieces around one shared board and sees each other's moves instantly. That's a completely different shape from a normal web request: instead of ask-and-answer, it's a long-lived open connection with constant little updates. Go handles thousands of those open connections cheaply, so the live board is its own small service. It's a second split, but for a different reason than the worker — this one is about staying connected, not about crunching.",

  "sync-async":
    "When you upload a demo, I don't make you sit and wait for it to parse — that could take minutes, and the request would just time out. Instead the upload returns immediately saying \"got it, parsing now,\" and the heavy work happens in the background. The app drops a job on a queue, the worker picks it up when it's ready, and the page updates when it's done. It's the difference between waiting on hold and getting a callback.",

  "polyglot-queue":
    "The queue that connects the app and the worker is deliberately simple: just a plain list in Redis, with each job written as basic JSON. I didn't use Laravel's built-in queue system, because that format only makes sense to Laravel — and my worker is written in Go. By using the plainest possible format, both languages can read and write the same jobs with no translation layer. The cost is I gave up Laravel's automatic retries and had to define the format myself, but that's the price of two languages sharing one queue.",

  "swappable-queue":
    "Even though the queue is just a Redis list today, the rest of the code never talks to Redis directly — it talks to an interface, a kind of contract that says \"put a job somewhere\" without saying where. So the day I outgrow a plain list and want something sturdier, I swap the one piece behind that contract and nothing else changes. It's a small bit of discipline now that buys me a cheap upgrade later.",

  "search-cqrs":
    "Some questions are painful to ask a normal database — \"all my AWP opening kills on Mirage\" would be a monster query. So I keep a second, search-optimized copy of the data in a tool built for exactly that. The important idea is that this copy is never the source of truth — it's a projection, rebuilt from the real database, and it can lag a moment behind. I trade perfect freshness for fast, flexible search, and I can always rebuild the copy from scratch if it drifts.",

  "shared-db":
    "Right now the app and the worker share one database, and that's a deliberate choice, not laziness. Splitting the database is the harder, more advanced move, and I wanted to learn the queue boundary first without taking on two hard problems at once. I'm being honest that it's a shortcut, and I've written down exactly when and why I'd split it later. Naming a shortcut as a shortcut is part of the study.",

  authz:
    "Logging in uses a token — you sign in, get a token, and send it with each request to prove who you are. What you're allowed to do is separate, and I made it editable at runtime: instead of permissions being hard-coded, they live in a grant matrix an admin can change without a code deploy. So promoting someone to \"can delete matches\" is a setting, not a release. Auth answers \"who are you\"; this answers \"what may you do\" — and the second one shouldn't require a developer.",

  i18n:
    "My backend services never return sentences meant for humans — they return short codes like \"file too large.\" The frontend is the only place that turns a code into words a person reads, in their language. That keeps the two backends, in two languages, from each having to know about translations. There's one place words live, and the servers stay language-neutral. It's a little more indirection, but it means adding a new language never touches the backend.",

  monorepo:
    "Everything lives in one git repository — the app, the worker, the frontend, all of it — but each keeps its own separate dependency list. The payoff is that when I change something that spans two services, like the queue's job format, I fix both sides in a single commit that can never get half-applied. One clone, one command to run the whole thing, but the services still stay cleanly un-tangled from each other.",

  "pub-sub":
    "There are two very different kinds of messages in the system. The queue is a command — it tells one specific worker \"parse this demo,\" and exactly one worker does it. The event channel is an announcement — it broadcasts \"a match just finished\" to whoever cares, and any number of listeners can react. Commands are one-to-one and about what to do; events are one-to-many and about what happened. Keeping them separate keeps the system loosely coupled — I can add a new reaction without touching whoever raised the event.",

  testing:
    "In a system of several services in different languages, the scariest bug isn't inside one service — it's the two of them disagreeing about the message format between them. So my most valuable test isn't a normal unit test; it's a shared example of the wire format that both sides check themselves against. If someone changes the format on one side, the other side's test fails immediately. I test the seams between services, because that's where things actually break.",

  orchestration:
    "On my laptop, one docker-compose file quietly does four jobs at once: it builds the images, runs the containers, wires up the network, and holds the config. That's perfect for development. In the cloud, those four jobs split apart and go to four different owners — a registry, a runner, a network layer, config management. Understanding that one convenient file is really four concerns bundled together is the whole lesson of what it takes to leave the laptop.",

  subscriber:
    "When a training gets scheduled, two completely different things need to happen: post to Discord, and email the players. Rather than cram both into one place, I announce the fact once on the event channel, and two independent listeners react — a tiny Go service posts to Discord, and Laravel sends the emails. The neat part is the announcer didn't change at all to add the second reaction. Each reaction lives where its tools already are, and I can add a third tomorrow without touching the other two.",

  pipeline:
    "Because everything is in one repo but they're really six separate services, my automated checks aren't one big pass-or-fail — they're six independent pipelines, and each only runs when its own files change. A frontend tweak doesn't waste time re-testing the Go services. The one clever exception is the shared message formats: changing those triggers every service that uses them, so both sides get re-checked together. The pipeline ends up mirroring the same boundaries the services have.",

  rag:
    "I built a feature that answers questions about our matches in plain English. The AI doesn't know our data, so instead of asking it to recall, I retrieve the relevant matches, kills, and trainings from our own database, hand them to the model along with the question, and let it write a grounded answer that cites each match. I fetch evidence two ways — by exact keyword and by meaning — so it catches both \"AWP kills on Mirage\" and vaguer questions like \"our comeback games.\" That's RAG: retrieval-augmented generation — an open-book exam for the AI.",

  observability:
    "When you upload a demo, the work touches three separate services in two languages — the app takes the file, a Go worker parses it, and another service posts to Discord. I already collect everyone's logs in one place and can pull up a single match's story by its ID. But logs tell you what happened, not how long each step took or what caused what. So I added tracing: every upload gets a trace ID that travels with it across all three services — riding inside the queue message and the event, since these don't talk over normal web requests — and they report their timing to one timeline I can open as a waterfall. What's still missing is the third piece, metrics: dashboards for overall rates and errors. Logs and traces are in; aggregate health is the next step.",

  'trust-boundary':
    "Security here is really about one question: what can the outside world reach? My answer is a hard shell — only one service, the gateway, is open to the internet. Everything else — the app, the parser, the databases, storage — lives on a private network no outsider can touch, and they talk to each other by name. At that one door I check everything: you need a valid token, sensitive actions are rate-limited, and uploaded files are validated before anything opens them. The honest trade-off is that inside the wall, the services trust each other completely — the parser will process any job it's handed, no questions asked. That's fine as long as the wall holds, but it's a single layer of defense, and a couple of developer-convenience doors (a database port, an admin dashboard with no login) would need closing before this ever went to production. I know exactly where those gaps are — naming them is half of taking security seriously.",

  'untrusted-parse':
    "The riskiest thing this app does is parse a demo file, because that file came from a stranger and it's read by a big, complex library that was never built to be safe against a deliberately evil file. So I defend it in layers, each catching what the last one misses. First, if the parser crashes on a broken file, I catch it and just mark the upload failed. Second, if a file is crafted to run forever or eat all the memory, a time limit and a memory limit stop it. Third — and this is the new part — the actual parsing runs in a separate throwaway process: I hand it only the file's bytes, nothing else, not even my database passwords, and if that file manages to break or hijack the parser, the damage dies with that little process instead of taking down the whole worker. The point isn't one perfect defense; it's a stack of them, so no single trick gets through. The last rung — fully jailing that process from the network and disk — is the next thing I'd add.",

  'split-db':
    "For a while the app and the parser shared one database — simpler to start, and I wanted to learn the queue between them first. Splitting it was the next step, and the real lesson is that it's not about tables, it's about deciding who owns what. I drew a line: the parser owns the analytics tables (the scoreboard, the kill data), and the app owns the matches list. Then I locked it down so the database itself won't let the parser write to the app's table. The catch is that the parser used to just reach over and update the match's status directly — which only worked because they shared one database. Now it can't. So instead it announces \"I finished match 39, here's the result,\" and the app, which owns that record, updates it. The convenience I gave up — one quick cross-table write — came back as a message to the owner. That's the whole trade: you lose the shortcuts a shared database quietly allowed, and you pay them back with events. Two honest loose ends remain: a dropped message can leave a match stuck on \"parsing,\" and I still need to flip the parser onto its restricted database login in the running setup.",

  'local-model':
    "The question-answering feature calls an AI model, and I'd built it so the model sits behind a plug — the app asks \"answer this with this evidence\" and doesn't care who answers. So I ran a second model on my own machine and plugged it into the same socket to see what the paid one was actually buying. For the part that converts text into numbers for meaning-based search, the local one wins outright: it's free, it's private, it runs in batches where slow doesn't matter, and it replaced a deliberately dumb stand-in that couldn't tell \"duel\" from \"fight.\" For actually writing the answer, it's more interesting. The small model kept the rules that matter — it cited real match IDs and copied player names exactly — but ignored the cosmetic ones, handing back a formatted report when I asked for a few plain sentences. And speed is a cliff, not a slope: about nine seconds when the model fits on the graphics card, about four and a half minutes when it doesn't and quietly falls back to the processor, with nothing in the response telling you which one you got. So local is the free and private option, not the better one — and the useful part is that swapping between them is one setting, so I could measure that instead of guessing.",
}
