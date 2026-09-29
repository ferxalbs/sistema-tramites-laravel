import type { LucideIcon } from 'lucide-react';
import { Monitor, Moon, Sun } from 'lucide-react';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';

export default function AppearanceToggleTab({
    className = '',
}: {
    className?: string;
}) {
    const { appearance, updateAppearance } = useAppearance();

    const tabs: { value: Appearance; icon: LucideIcon; label: string }[] = [
        { value: 'light', icon: Sun, label: 'Light' },
        { value: 'dark', icon: Moon, label: 'Dark' },
        { value: 'system', icon: Monitor, label: 'System' },
    ];

    return (
        <ToggleGroup
            value={[appearance]}
            onValueChange={(val) => {
                if (val && val[0]) {
                    updateAppearance(val[0] as Appearance);
                }
            }}
            variant="outline"
            className={className}
        >
            {tabs.map(({ value, icon: Icon, label }) => (
                <ToggleGroupItem key={value} value={value} aria-label={label}>
                    <Icon className="size-4" />
                    <span>{label}</span>
                </ToggleGroupItem>
            ))}
        </ToggleGroup>
    );
}
