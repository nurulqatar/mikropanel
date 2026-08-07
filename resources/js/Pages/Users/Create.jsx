import AppLayout from '@/Layouts/AppLayout';
import { Head } from '@inertiajs/react';
import UserForm from './UserForm';

export default function Create({
    permissionGroups = [],
}) {
    return (
        <AppLayout title="Add Panel User">
            <Head title="Add Panel User" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">
                        Add Panel User
                    </h1>

                    <p className="mt-1 text-slate-500">
                        Create an administrator or operator account
                    </p>
                </div>

                <UserForm
                    permissionGroups={
                        permissionGroups
                    }
                />
            </div>
        </AppLayout>
    );
}
