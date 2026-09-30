export const logoutHistoryGuardKey = 'tramites:logged-out-history';

export function markLoggedOut(): void {
    if (typeof window !== 'undefined') {
        window.sessionStorage.setItem(logoutHistoryGuardKey, '1');
    }
}
