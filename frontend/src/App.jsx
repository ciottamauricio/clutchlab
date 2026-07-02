import DashboardPage from './pages/DashboardPage'
import './App.css'

// Composition root. A single page for now; when a second route appears this is where
// the router goes (react-router is deferred until there's a real second route).
export default function App() {
  return <DashboardPage />
}
