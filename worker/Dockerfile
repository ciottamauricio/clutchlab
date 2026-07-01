FROM golang:1.24-alpine
# Pinned: air >= 1.65 requires Go 1.25, but this stack is on Go 1.24.
RUN go install github.com/air-verse/air@v1.61.7
WORKDIR /app
COPY go.* ./
RUN go mod download
COPY . .
CMD ["air"]
