import { useForm, usePage } from '@inertiajs/vue3';

function withoutFinance(data) {
    if (Array.isArray(data)) return data.map(withoutFinance);
    if (data === null || typeof data !== 'object' || (typeof File !== 'undefined' && data instanceof File)) return data;
    return Object.fromEntries(Object.entries(data)
        .filter(([key]) => !/(?:^|_)(?:fee|fees|finance|payment|payments|invoice|receipt|refund|bank|upi|price|pricing|cost|amount|paid|due|collected|balance|revenue|expense|payable|waiver|arrears|remittance|tax|discount)(?:_|$)/i.test(key.replace(/([a-z])([A-Z])/g, '$1_$2')))
        .map(([key, value]) => [key, withoutFinance(value)]));
}

export function useOperationalEventForm(data) {
    const page = usePage();
    const form = useForm(data);
    form.transform(values => page.props.operationalEventAdmin ? withoutFinance(values) : values);
    return form;
}
