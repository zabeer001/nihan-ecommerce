import React from 'react';
import Link from '@/shared/CachedLink';

function UnauthorizedPage() {
    return (
        <div className="mx-auto max-w-3xl space-y-6">
            <section className="card bg-base-100 shadow-xl">
                <div className="card-body">
                    <h1 className="text-3xl font-extrabold">Unauthorized</h1>
                    <p className="mt-2 text-sm text-base-content/70">
                        You do not have permission to access this page.
                    </p>
                    <div className="mt-4">
                        <Link href="/dashboard" className="btn btn-primary">
                            Go To Dashboard
                        </Link>
                    </div>
                </div>
            </section>
        </div>
    );
}

export default UnauthorizedPage;
