import { usePage } from '@inertiajs/vue3';

export function useCan() {
    const page = usePage();

    const can = (permission) => (page.props.auth.user?.permissions ?? []).includes(permission);

    return { can };
}
