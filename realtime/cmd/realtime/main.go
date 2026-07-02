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
	log.Fatal(http.ListenAndServe(cfg.HTTPAddr, mux))
}
