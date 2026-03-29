import type { Config } from 'tailwindcss'
import animate from 'tailwindcss-animate'

const config: Config = {
  darkMode: ['class'],
  content: ['./index.html', './src/**/*.{ts,tsx}'],
  theme: {
    extend: {
      fontFamily: {
        sans: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'Fira Code', 'monospace'],
      },
      colors: {
        primary: {
          DEFAULT: 'hsl(var(--color-primary))',
          light:   'hsl(var(--color-primary-light))',
          dark:    'hsl(var(--color-primary-dark))',
        },
        surface: {
          base:    'hsl(var(--color-bg-base))',
          DEFAULT: 'hsl(var(--color-bg-surface))',
          subtle:  'hsl(var(--color-bg-subtle))',
          muted:   'hsl(var(--color-bg-muted))',
        },
        content: {
          DEFAULT:   'hsl(var(--color-text-primary))',
          secondary: 'hsl(var(--color-text-secondary))',
          muted:     'hsl(var(--color-text-muted))',
          inverse:   'hsl(var(--color-text-inverse))',
        },
        success: {
          DEFAULT: 'hsl(var(--color-success))',
          light:   'hsl(var(--color-success-light))',
        },
        warning: {
          DEFAULT: 'hsl(var(--color-warning))',
          light:   'hsl(var(--color-warning-light))',
        },
        danger: {
          DEFAULT: 'hsl(var(--color-danger))',
          light:   'hsl(var(--color-danger-light))',
        },
        info: {
          DEFAULT: 'hsl(var(--color-info))',
          light:   'hsl(var(--color-info-light))',
        },
        stage: {
          prospect:    'hsl(var(--color-stage-prospect))',
          qualify:     'hsl(var(--color-stage-qualify))',
          proposal:    'hsl(var(--color-stage-proposal))',
          negotiation: 'hsl(var(--color-stage-negotiation))',
          won:         'hsl(var(--color-stage-won))',
          lost:        'hsl(var(--color-stage-lost))',
        },
        // shadcn/ui compat
        background:  'hsl(var(--color-bg-base))',
        foreground:  'hsl(var(--color-text-primary))',
        border:      'hsl(var(--color-bg-muted))',
        input:       'hsl(var(--color-bg-subtle))',
        ring:        'hsl(var(--color-primary))',
        card: {
          DEFAULT:    'hsl(var(--color-bg-surface))',
          foreground: 'hsl(var(--color-text-primary))',
        },
        muted: {
          DEFAULT:    'hsl(var(--color-bg-muted))',
          foreground: 'hsl(var(--color-text-muted))',
        },
        destructive: {
          DEFAULT:    'hsl(var(--color-danger))',
          foreground: 'hsl(var(--color-text-inverse))',
        },
      },
      spacing: {
        '1':  '0.25rem',
        '2':  '0.5rem',
        '3':  '0.75rem',
        '4':  '1rem',
        '5':  '1.25rem',
        '6':  '1.5rem',
        '8':  '2rem',
        '10': '2.5rem',
        '12': '3rem',
        '16': '4rem',
        '20': '5rem',
      },
      borderRadius: {
        sm:   '0.25rem',
        md:   '0.5rem',
        lg:   '0.75rem',
        xl:   '1rem',
        '2xl':'1.5rem',
        full: '9999px',
      },
      boxShadow: {
        'soft-sm':    '0 1px 2px rgba(0,0,0,0.04), 0 1px 6px rgba(0,0,0,0.04)',
        'soft-md':    '0 4px 6px rgba(0,0,0,0.04), 0 2px 12px rgba(0,0,0,0.06)',
        'soft-lg':    '0 8px 16px rgba(0,0,0,0.06), 0 2px 20px rgba(0,0,0,0.04)',
        'soft-xl':    '0 20px 40px rgba(0,0,0,0.10), 0 4px 16px rgba(0,0,0,0.06)',
        'soft-hover': '0 8px 24px rgba(0,0,0,0.10), 0 2px 8px rgba(0,0,0,0.06)',
        'focus':      '0 0 0 3px rgba(37,99,235,0.25)',
      },
      transitionDuration: {
        fast:   '150ms',
        base:   '200ms',
        slow:   '300ms',
        slower: '500ms',
      },
      width: {
        sidebar:          '240px',
        'sidebar-collapsed': '64px',
      },
      height: {
        header: '60px',
      },
      screens: {
        sm:  '375px',
        md:  '768px',
        lg:  '1024px',
        xl:  '1280px',
        '2xl': '1440px',
      },
    },
  },
  plugins: [animate],
}

export default config
