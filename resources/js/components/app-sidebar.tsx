import { Link, usePage } from '@inertiajs/react';
import {
    ClipboardList,
    LayoutGrid,
    Package,
    Truck,
    Users,
    Wallet,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const catalog: Array<NavItem & { permission?: string }> = [
    {
        title: 'Board',
        href: dashboard(),
        icon: LayoutGrid,
        permission: 'dashboard.read',
    },
    {
        title: 'Dispatch',
        href: '/dispatch',
        icon: ClipboardList,
        permission: 'dispatch.manage',
    },
    {
        title: 'Shipments',
        href: '/shipments',
        icon: Package,
        permission: 'shipments.read',
    },
    {
        title: 'Drive',
        href: '/drive',
        icon: Truck,
        permission: 'shipments.driver_action',
    },
    {
        title: 'Customers',
        href: '/customers',
        icon: Users,
        permission: 'customers.read',
    },
    {
        title: 'Drivers',
        href: '/drivers',
        icon: Truck,
        permission: 'drivers.read',
    },
    {
        title: 'Vehicles',
        href: '/vehicles',
        icon: Truck,
        permission: 'vehicles.read',
    },
    {
        title: 'Trips',
        href: '/trips',
        icon: ClipboardList,
        permission: 'trips.read',
    },
    {
        title: 'Exceptions',
        href: '/exceptions',
        icon: ClipboardList,
        permission: 'exceptions.read',
    },
    {
        title: 'Billing',
        href: '/billing',
        icon: Wallet,
        permission: 'finance.read',
    },
    {
        title: 'Reports',
        href: '/reports',
        icon: LayoutGrid,
        permission: 'reports.read',
    },
    {
        title: 'Notices',
        href: '/notifications',
        icon: ClipboardList,
        permission: 'notifications.read',
    },
    {
        title: 'Organization',
        href: '/settings/organization',
        icon: Users,
        permission: 'settings.manage',
    },
];

export function AppSidebar() {
    const { auth } = usePage().props;
    const permissions = auth.permissions ?? [];
    const mainNavItems = catalog.filter(
        (item) =>
            !item.permission ||
            auth.role === 'owner' ||
            permissions.includes(item.permission),
    );

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
