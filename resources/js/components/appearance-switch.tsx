import { Moon, Sun } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

export default function AppearanceSwitch({
    className,
}: {
    className?: string;
}) {
    const { resolvedAppearance, updateAppearance } = useAppearance();
    const dark = resolvedAppearance === 'dark';

    return (
        <Button
            type="button"
            variant="outline"
            aria-label={dark ? 'Cambiar a vino claro' : 'Cambiar a guinda'}
            title={dark ? 'Cambiar a vino claro' : 'Cambiar a guinda'}
            onClick={() => updateAppearance(dark ? 'light' : 'dark')}
            className={cn(
                'h-9 shrink-0 gap-2 border-primary/40 bg-card px-3 font-semibold text-card-foreground shadow-sm hover:bg-accent hover:text-accent-foreground',
                className,
            )}
        >
            {dark ? <Sun className="size-4" /> : <Moon className="size-4" />}
            <span>{dark ? 'Vino claro' : 'Guinda'}</span>
        </Button>
    );
}
