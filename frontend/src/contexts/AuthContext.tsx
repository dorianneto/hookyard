import { createContext, useContext, useEffect, useState } from 'react'
import type { ReactNode } from 'react'

export interface User {
  id: string
  email: string
  name: string | null
  status: 'pending_payment' | 'active' | 'cancelled'
}

interface AuthContextValue {
  user: User | null
  isLoading: boolean
  login: (email: string, password: string) => Promise<void>
  logout: () => Promise<void>
  updateUser: (updated: User) => void
}

const AuthContext = createContext<AuthContextValue | null>(null)

export function AuthProvider({ children }: { children: ReactNode }) {
  const [user, setUser] = useState<User | null>(() => {
    const stored = localStorage.getItem('waas_user')
    return stored ? (JSON.parse(stored) as User) : null
  })
  const [isLoading, setIsLoading] = useState(true)

  useEffect(() => {
    fetch('/api/v1/me')
      .then((res) => {
        if (res.ok) return res.json() as Promise<User>
        throw new Error('unauthenticated')
      })
      .then((data) => {
        setUser(data)
        localStorage.setItem('waas_user', JSON.stringify(data))
      })
      .catch(() => {
        setUser(null)
        localStorage.removeItem('waas_user')
      })
      .finally(() => setIsLoading(false))
  }, [])

  useEffect(() => {
    const handler = () => {
      setUser(null)
      localStorage.removeItem('waas_user')
    }
    window.addEventListener('auth:unauthorized', handler)
    return () => window.removeEventListener('auth:unauthorized', handler)
  }, [])

  const login = async (email: string, password: string): Promise<void> => {
    const res = await fetch('/login', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ email, password }),
    })

    if (!res.ok) {
      const data = await res.json().catch(() => ({}))
      throw new Error((data as { error?: string }).error ?? 'Login failed.')
    }

    const data = (await res.json()) as User
    setUser(data)
    localStorage.setItem('waas_user', JSON.stringify(data))
  }

  const logout = async (): Promise<void> => {
    await fetch('/logout', { method: 'POST' }).catch(() => {})
    setUser(null)
    localStorage.removeItem('waas_user')
  }

  const updateUser = (updated: User): void => {
    setUser(updated)
    localStorage.setItem('waas_user', JSON.stringify(updated))
  }

  return (
    <AuthContext.Provider value={{ user, isLoading, login, logout, updateUser }}>
      {children}
    </AuthContext.Provider>
  )
}

export function useAuth(): AuthContextValue {
  const ctx = useContext(AuthContext)
  if (!ctx) throw new Error('useAuth must be used within AuthProvider')
  return ctx
}
