import { Badge } from '@/components/ui/badge';

export default function TramiteStatusBadge({
    estado,
    label,
}: {
    estado: string;
    label: string;
}) {
    const variant = estado === 'rechazado'
        ? 'destructive'
        : ['aprobado', 'entregado', 'cerrado'].includes(estado)
            ? 'default'
            : ['en_revision', 'digitalizado', 'documento_final_generado'].includes(estado)
                ? 'outline'
                : 'secondary';

    return <Badge variant={variant}>{label}</Badge>;
}
