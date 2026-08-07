import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import UserForm from './UserForm';

export default function Edit({
    panelUser,
    permissionGroups = [],
}) {
    return (
        <AppLayout title="Edit Panel User">
            <Head title="Edit Panel User" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">
                        Edit Panel User
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Update account access and permissions
                    </p>
                </div>

                <UserForm
                    panelUser={panelUser}
                    permissionGroups={
                        permissionGroups
                    }
                />
            </div>
        </AppLayout>
    );
}
