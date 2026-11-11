import { createContext, useContext } from 'react';

export const PermissionsContext = createContext(null);

export function usePermissions() {
    const permissions = useContext(PermissionsContext);

    return {
        loading: permissions === null,
        can: permission => permissions?.includes(permission) ?? false,
    };
}
