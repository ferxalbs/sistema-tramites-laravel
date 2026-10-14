import type { HTMLAttributes } from 'react';
import { cn } from 'cn';

export default function AppLogoIcon({
    className,
    ...props
}: HTMLAttributes<HTMLSpanElement>) {
    return (
        <span
            aria-hidden="true"
            className={cn(
                'relative inline-flex shrink-0 overflow-hidden rounded-lg bg-[#3b0a12] align-middle',
                className,
            )}
            {...props}
        >
            <img
                src="/brand/dsi-seoane.jpg"
                alt=""
                draggable={false}
                className="absolute inset-0 size-full scale-[1.6] object-cover"
            />
        </span>
    );
}
