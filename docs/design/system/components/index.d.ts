export interface ButtonProps { variant?: 'solid' | 'outline'; href?: string; type?: 'button' | 'submit'; disabled?: boolean; onClick?: () => void; children: React.ReactNode }
export interface BadgeProps { tone?: 'primary' | 'success' | 'danger'; href?: string; children: React.ReactNode }
export interface CardProps { title?: string; image?: string; imageAlt?: string; children: React.ReactNode }
export interface FieldProps { id: string; label?: string; kind?: 'text' | 'email' | 'search' | 'url' | 'number' | 'select' | 'textarea'; options?: string[]; placeholder?: string; hint?: string; error?: string; disabled?: boolean; value?: string; defaultValue?: string; onChange?: (e: React.ChangeEvent<any>) => void }
export interface AlertProps { tone?: 'info' | 'success' | 'warning' | 'danger'; label?: string; children: React.ReactNode }
export interface ScoreMeterProps { score: number | null; label?: string }
