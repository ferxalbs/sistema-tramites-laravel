import {
    Field,
    FieldDescription,
    FieldError,
    FieldLabel,
} from '@/components/ui/field';
import {
    Select,
    SelectContent,
    SelectGroup,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';

export default function SelectField({
    id,
    label,
    name,
    value,
    defaultValue,
    required,
    options,
    description,
    error,
    onValueChange,
}: {
    id: string;
    label: string;
    name?: string;
    value?: string;
    defaultValue?: string;
    required?: boolean;
    options: Record<string, string>;
    description?: string;
    error?: string;
    onValueChange?: (value: string | null) => void;
}) {
    const items = Object.entries(options).map(([optionValue, optionLabel]) => ({
        value: optionValue,
        label: optionLabel,
    }));

    return (
        <Field data-invalid={error ? true : undefined}>
            <FieldLabel htmlFor={id}>{label}</FieldLabel>
            <Select
                items={items}
                name={name ?? id}
                value={value}
                defaultValue={defaultValue}
                required={required}
                onValueChange={onValueChange}
            >
                <SelectTrigger
                    id={id}
                    className="w-full"
                    aria-describedby={
                        [
                            description ? `${id}-description` : undefined,
                            error ? `${id}-error` : undefined,
                        ]
                            .filter(Boolean)
                            .join(' ') || undefined
                    }
                    aria-invalid={error ? true : undefined}
                >
                    <SelectValue />
                </SelectTrigger>
                <SelectContent>
                    <SelectGroup>
                        {items.map((item) => (
                            <SelectItem key={item.value} value={item.value}>
                                {item.label}
                            </SelectItem>
                        ))}
                    </SelectGroup>
                </SelectContent>
            </Select>
            {description && (
                <FieldDescription id={`${id}-description`}>
                    {description}
                </FieldDescription>
            )}
            <FieldError id={`${id}-error`}>{error}</FieldError>
        </Field>
    );
}

TramiteCreate.layout = {
    breadcrumbs: [
        { title: 'Bandeja de trámites', href: TramiteController.index() },
    ],
};
