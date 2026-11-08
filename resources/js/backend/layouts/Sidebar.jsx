import React, { useEffect, useMemo, useState } from 'react';
import { usePage } from '@inertiajs/react';
import Link from '@/shared/CachedLink';
import useAuth from '../../shared/hooks/useAuth';
import {
    LayoutDashboard,
    ClipboardList,
    UsersRound,
    FileText,
    Receipt,
    Settings,
    Truck,
    FileCheck2,
    WalletCards,
    PackageSearch,
} from 'lucide-react';

const menuItems = [
    { label: 'Dashboard', href: '/dashboard', icon: LayoutDashboard },
    { label: 'Categories', href: '/dashboard/categories', icon: ClipboardList, roles: ['admin', 'superadmin'] },
    { label: 'Products', href: '/dashboard/products', icon: PackageSearch, roles: ['admin', 'superadmin'] },
    { label: 'Inventory', href: '/dashboard/inventory', icon: WalletCards, roles: ['admin', 'superadmin'] },
    { label: 'Orders', href: '/dashboard/orders', icon: Receipt, roles: ['admin', 'superadmin'] },
   

];

const SIDEBAR_DROPDOWN_STORAGE_KEY = 'backend_sidebar_open_dropdowns';
const activeMenuClass = 'bg-primary text-primary-content shadow-sm hover:bg-primary hover:text-primary-content';
const inactiveMenuClass = 'hover:bg-base-200';

function pathname(url) {
    return String(url || '').split('?')[0].split('#')[0];
}

function itemIsActive(item, currentPath) {
    if (item.children) {
        return item.children.some((child) => currentPath === child.href || currentPath.startsWith(`${child.href}/`));
    }

    if (item.href === '/dashboard') {
        return currentPath === item.href;
    }

    return currentPath === item.href || currentPath.startsWith(`${item.href}/`);
}

function mergeOpenDropdowns(current, activeDropdowns) {
    let hasChanged = false;
    const next = { ...current };

    Object.entries(activeDropdowns).forEach(([label, isOpen]) => {
        if (next[label] !== isOpen) {
            next[label] = isOpen;
            hasChanged = true;
        }
    });

    return hasChanged ? next : current;
}

function Sidebar() {
    const { url } = usePage();
    const { role } = useAuth();
    const currentPath = pathname(url);
    const [openDropdowns, setOpenDropdowns] = useState(() => {
        try {
            return JSON.parse(localStorage.getItem(SIDEBAR_DROPDOWN_STORAGE_KEY) || '{}');
        } catch {
            return {};
        }
    });
    const visibleItems = useMemo(() => menuItems.filter((item) => !item.roles || item.roles.includes(role)), [role]);

    useEffect(() => {
        const activeDropdowns = visibleItems
            .filter((item) => item.children && itemIsActive(item, currentPath))
            .reduce((next, item) => ({ ...next, [item.label]: true }), {});

        if (Object.keys(activeDropdowns).length === 0) return;

        setOpenDropdowns((current) => mergeOpenDropdowns(current, activeDropdowns));
    }, [currentPath, visibleItems]);

    useEffect(() => {
        localStorage.setItem(SIDEBAR_DROPDOWN_STORAGE_KEY, JSON.stringify(openDropdowns));
    }, [openDropdowns]);

    return (
        <aside className="drawer-side z-20">
            <label htmlFor="admin-drawer" aria-label="close sidebar" className="drawer-overlay" />
            <div className="min-h-full w-72 border-r border-base-300 bg-base-100 p-4">
                <div className="card mb-6 border border-base-300 bg-base-200 shadow-sm">
                    <div className="card-body p-4">
                        <p className="text-xs uppercase tracking-[0.18em] text-base-content/70">Control Center</p>
                        <h2 className="text-2xl font-extrabold">Dashboard</h2>
                    </div>
                </div>

                <ul className="menu w-full rounded-box p-0">
                    {visibleItems.map((item) => {
                        const Icon = item.icon;
                        const isActive = itemIsActive(item, currentPath);

                        if (item.children) {
                            const isOpen = Boolean(openDropdowns[item.label]);

                            return (
                                <li key={item.label} className="w-full">
                                    <details
                                        open={isOpen}
                                        onToggle={(event) => {
                                            const nextOpen = event.currentTarget.open;
                                            setOpenDropdowns((current) => (
                                                current[item.label] === nextOpen
                                                    ? current
                                                    : { ...current, [item.label]: nextOpen }
                                            ));
                                        }}
                                    >
                                        <summary
                                            className={`flex w-full items-center gap-3 rounded-lg px-3 py-3 ${
                                                isActive ? activeMenuClass : inactiveMenuClass
                                            }`}
                                        >
                                            <span
                                                className={`flex h-9 w-9 items-center justify-center rounded-lg ${
                                                    isActive ? 'bg-base-100/30' : 'bg-base-200 text-base-content/70'
                                                }`}
                                            >
                                                <Icon size={18} strokeWidth={2.2} />
                                            </span>
                                            <span className="flex-1 text-sm font-medium tracking-[0.01em]">{item.label}</span>
                                        </summary>
                                        <ul className="ml-12 mt-1 border-l border-base-300 pl-3">
                                            {item.children.map((child) => {
                                                const childIsActive = currentPath === child.href || currentPath.startsWith(`${child.href}/`);

                                                return (
                                                    <li key={child.label}>
                                                        <Link
                                                            href={child.href}
                                                            className={`rounded-lg px-3 py-2 text-sm ${
                                                                childIsActive ? activeMenuClass : inactiveMenuClass
                                                            }`}
                                                        >
                                                            {child.label}
                                                        </Link>
                                                    </li>
                                                );
                                            })}
                                        </ul>
                                    </details>
                                </li>
                            );
                        }

                        return (
                            <li key={item.label} className="w-full">
                                <Link
                                    href={item.href}
                                    className={`flex w-full items-center gap-3 rounded-lg px-3 py-3 ${
                                        item.variant === 'home'
                                            ? 'mt-2 border border-primary/25 bg-primary/5 hover:bg-primary/10'
                                            : ''
                                    } ${isActive ? activeMenuClass : inactiveMenuClass}`}
                                >
                                    <span
                                        className={`flex h-9 w-9 items-center justify-center rounded-lg ${
                                            isActive
                                                ? 'bg-base-100/30'
                                                : item.variant === 'home'
                                                    ? 'bg-primary/10 text-primary'
                                                    : 'bg-base-200 text-base-content/70'
                                        }`}
                                    >
                                        <Icon size={18} strokeWidth={2.2} />
                                    </span>
                                    <span className="text-sm font-medium tracking-[0.01em]">{item.label}</span>
                                </Link>
                            </li>
                        );
                    })}
                </ul>
            </div>
        </aside>
    );
}

export default Sidebar;
