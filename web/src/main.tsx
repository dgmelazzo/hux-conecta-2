import React from 'react'
import ReactDOM from 'react-dom/client'

function App() {
  return (
    <div style={{
      display:'flex', flexDirection:'column', alignItems:'center',
      justifyContent:'center', height:'100vh', fontFamily:'sans-serif',
      background:'#F8FAFC'
    }}>
      <div style={{
        background:'#fff', padding:'48px 64px', borderRadius:'16px',
        boxShadow:'0 4px 24px rgba(0,0,0,0.08)', textAlign:'center'
      }}>
        <div style={{fontSize:'48px', marginBottom:'16px'}}>🏢</div>
        <h1 style={{color:'#2563EB', margin:'0 0 8px', fontSize:'28px'}}>Conecta CRM</h1>
        <p style={{color:'#475569', margin:'0 0 24px'}}>Plataforma de Gestão para Associações</p>
        <div style={{
          background:'#ECFDF5', color:'#065f46', padding:'8px 16px',
          borderRadius:'8px', fontSize:'14px', display:'inline-block'
        }}>
          ✅ Sistema online — Fase 1 concluída
        </div>
        <div style={{marginTop:'24px', color:'#94A3B8', fontSize:'13px'}}>
          HUX Participações · ACIC-DF · 2026
        </div>
      </div>
    </div>
  )
}

ReactDOM.createRoot(document.getElementById('root')!).render(<App />)
