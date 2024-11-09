import { BrowserRouter } from 'react-router-dom'

ReactDOM.createRoot(document.getElementById('root')).render(
  <React.StrictMode>
    <BrowserRouter basename="/givehub">
      <App />
    </BrowserRouter>
  </React.StrictMode>
)
