import React, { useState } from 'react'
import ReactDOM from 'react-dom/client'
import './styles/globals.css'

const API = import.meta.env.VITE_API_URL ?? 'https://crm.acicdf.org.br/api'

function LoginPage() {
  const [email, setEmail] = useState('')
  const [senha, setSenha] = useState('')
  const [erro, setErro] = useState('')
  const [loading, setLoading] = useState(false)

  async function handleLogin(e: React.FormEvent) {
    e.preventDefault()
    setErro('')
    setLoading(true)
    try {
      const res = await fetch(`${API}/auth/login`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, senha }),
      })
      const data = await res.json()
      if (!data.ok) { setErro(data.error ?? 'Credenciais inválidas'); return }
      localStorage.setItem('crm_token', data.data.token)
      localStorage.setItem('crm_user', JSON.stringify(data.data.user))
      window.location.href = '/dashboard'
    } catch {
      setErro('Erro ao conectar com o servidor')
    } finally {
      setLoading(false)
    }
  }

  return (
    <div style={{ display: 'flex', minHeight: '100vh', fontFamily: 'Inter, sans-serif' }}>
      {/* Lado esquerdo */}
      <div style={{
        flex: 1, background: 'linear-gradient(135deg, #1e3a8a 0%, #2563eb 60%, #3b82f6 100%)',
        display: 'flex', flexDirection: 'column', justifyContent: 'center',
        padding: '48px', color: '#fff'
      }}>
        <div style={{ display: 'flex', alignItems: 'center', gap: '12px', marginBottom: '48px' }}>
          <div style={{
            width: '40px', height: '40px', background: 'rgba(255,255,255,0.2)',
            borderRadius: '10px', display: 'flex', alignItems: 'center', justifyContent: 'center', fontSize: '20px'
          }}>🏢</div>
          <span style={{ fontSize: '20px', fontWeight: 700 }}>Conecta <span style={{ fontWeight: 300 }}>CRM</span></span>
        </div>
        <h1 style={{ fontSize: '42px', fontWeight: 700, lineHeight: 1.2, marginBottom: '16px' }}>
          Gestão de Associações<br />
          <span style={{ fontWeight: 300, color: '#93c5fd' }}>simplificada.</span>
        </h1>
        <p style={{ color: '#bfdbfe', fontSize: '16px', marginBottom: '40px', maxWidth: '380px' }}>
          Plataforma SaaS white-label para associações comerciais. Pipeline, cobranças e relacionamento em um só lugar.
        </p>
        {['Gestão multi-tenant de associados', 'Cobranças via PIX, Boleto e Cartão',
          'Pipeline visual de prospecção', 'Régua de cobrança automática', 'Integração com Conecta 2.0'
        ].map(item => (
          <div key={item} style={{ display: 'flex', alignItems: 'center', gap: '10px', marginBottom: '12px' }}>
            <div style={{ width: '6px', height: '6px', borderRadius: '50%', background: '#60a5fa' }} />
            <span style={{ color: '#dbeafe', fontSize: '14px' }}>{item}</span>
          </div>
        ))}
        <div style={{ marginTop: 'auto', paddingTop: '48px', color: '#93c5fd', fontSize: '13px' }}>
          HUX Participações · v1.0 · 2026
        </div>
      </div>

      {/* Lado direito */}
      <div style={{
        width: '480px', display: 'flex', flexDirection: 'column',
        justifyContent: 'center', padding: '48px', background: '#fff'
      }}>
        <div style={{ marginBottom: '32px' }}>
          <div style={{
            display: 'inline-flex', alignItems: 'center', gap: '8px',
            background: '#eff6ff', color: '#1d4ed8', padding: '6px 12px',
            borderRadius: '6px', fontSize: '12px', fontWeight: 500, marginBottom: '16px'
          }}>PORTAL DE ACESSO</div>
          <h2 style={{ fontSize: '28px', fontWeight: 700, color: '#0f172a', margin: '0 0 8px' }}>
            Bem-vindo de volta
          </h2>
          <p style={{ color: '#64748b', fontSize: '14px', margin: 0 }}>
            Entre com suas credenciais para acessar o painel
          </p>
        </div>

        {/* Tenant badge */}
        <div style={{
          display: 'flex', alignItems: 'center', gap: '10px', padding: '12px 16px',
          border: '1px solid #e2e8f0', borderRadius: '10px', marginBottom: '24px'
        }}>
          <div style={{
            width: '32px', height: '32px', background: '#1d4ed8', borderRadius: '8px',
            display: 'flex', alignItems: 'center', justifyContent: 'center',
            color: '#fff', fontSize: '12px', fontWeight: 700
          }}>AC</div>
          <div>
            <div style={{ fontWeight: 600, fontSize: '14px', color: '#0f172a' }}>ACIC-DF</div>
            <div style={{ fontSize: '12px', color: '#94a3b8' }}>acicdf.crm</div>
          </div>
        </div>

        {erro && (
          <div style={{
            background: '#fef2f2', border: '1px solid #fecaca', color: '#dc2626',
            padding: '10px 14px', borderRadius: '8px', fontSize: '13px', marginBottom: '16px',
            display: 'flex', alignItems: 'center', gap: '8px'
          }}>⚠ {erro}</div>
        )}

        <form onSubmit={handleLogin}>
          <div style={{ marginBottom: '16px' }}>
            <label style={{ display: 'block', fontSize: '13px', fontWeight: 500, color: '#374151', marginBottom: '6px' }}>
              E-mail
            </label>
            <div style={{ position: 'relative' }}>
              <span style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: '#94a3b8' }}>✉</span>
              <input
                type="email" value={email} onChange={e => setEmail(e.target.value)} required
                placeholder="seu@email.com.br"
                style={{
                  width: '100%', padding: '10px 12px 10px 36px', border: '1px solid #e2e8f0',
                  borderRadius: '8px', fontSize: '14px', outline: 'none', boxSizing: 'border-box',
                  fontFamily: 'inherit'
                }}
              />
            </div>
          </div>

          <div style={{ marginBottom: '24px' }}>
            <label style={{ display: 'block', fontSize: '13px', fontWeight: 500, color: '#374151', marginBottom: '6px' }}>
              Senha
            </label>
            <div style={{ position: 'relative' }}>
              <span style={{ position: 'absolute', left: '12px', top: '50%', transform: 'translateY(-50%)', color: '#94a3b8' }}>🔒</span>
              <input
                type="password" value={senha} onChange={e => setSenha(e.target.value)} required
                placeholder="••••••••"
                style={{
                  width: '100%', padding: '10px 12px 10px 36px', border: '1px solid #e2e8f0',
                  borderRadius: '8px', fontSize: '14px', outline: 'none', boxSizing: 'border-box',
                  fontFamily: 'inherit'
                }}
              />
            </div>
          </div>

          <button
            type="submit" disabled={loading}
            style={{
              width: '100%', padding: '12px', background: loading ? '#93c5fd' : '#1d4ed8',
              color: '#fff', border: 'none', borderRadius: '8px', fontSize: '15px',
              fontWeight: 600, cursor: loading ? 'not-allowed' : 'pointer', fontFamily: 'inherit'
            }}
          >
            {loading ? 'Entrando...' : 'Entrar no painel →'}
          </button>
        </form>

        <div style={{ marginTop: '24px', textAlign: 'center', fontSize: '13px', color: '#94a3b8' }}>
          Problemas de acesso? <a href="mailto:suporte@hux.com.br" style={{ color: '#2563eb' }}>Falar com suporte</a>
        </div>
      </div>
    </div>
  )
}

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <LoginPage />
  </React.StrictMode>
)
