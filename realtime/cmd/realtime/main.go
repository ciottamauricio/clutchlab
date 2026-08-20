package main

import (
	"log"
	"net/http"

	"clutchlab/realtime/internal/config"
	"clutchlab/realtime/internal/db"
	"clutchlab/realtime/internal/hub"
	"clutchlab/realtime/internal/store"
	"clutchlab/realtime/internal/ws"
)

func main() {
	log.SetFlags(log.LstdFlags | log.Lmsgprefix)
	log.SetPrefix("[realtime] ")

	cfg := config.Load()

	conn, err := db.Connect(cfg.DSN())
	if err != nil {
		log.Fatal(err)
	}
	defer conn.Close()
	log.Print("connected to postgres")

	handler := ws.NewHandler(hub.New(), store.New(conn))

	mux := http.NewServeMux()
	mux.Handle("/realtime/tactics/", handler)
	mux.HandleFunc("/realtime/health", func(w http.ResponseWriter, _ *http.Request) {
		w.WriteHeader(http.StatusOK)
	})

	log.Printf("listening on %s", cfg.HTTPAddr)
	// Plain HTTP is correct here, and the reason lives in files a static analyser
	// cannot see: this service publishes no ports (docker-compose.yml), so it is
	// reachable only on clutchnet, and TLS terminates at nginx, which proxies
	// /realtime/ and upgrades the websocket. Serving TLS on this hop would mean
	// certs on an internal bridge network that never leaves the host.
	// nosemgrep: go.lang.security.audit.net.use-tls.use-tls
	log.Fatal(http.ListenAndServe(cfg.HTTPAddr, mux))
}
