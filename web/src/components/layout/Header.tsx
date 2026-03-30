import { useAuthStore } from '@/stores/authStore'

export default function Header() {
  const { user, logout } = useAuthStore()
  return (
    <header className="h-14 border-b bg-white flex items-center justify-between px-6">
      <span className="font-semibold text-gray-700">Conecta CRM</span>
      <div className="flex items-center gap-3">
        <span className="text-sm text-gray-500">{user?.nome}</span>
        <button onClick={logout} className="text-sm text-red-500 hover:underline">Sair</button>
      </div>
    </header>
  )
}
