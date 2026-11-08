import React from 'react';
import { Sparkles, ShieldCheck } from 'lucide-react';

function ReportsHeroSection({ userRole, qaRate }) {
  return (
    <section className="overflow-hidden rounded-[2rem] border border-base-300 bg-base-100 shadow-2xl">
      <div className="p-4 sm:p-8 lg:p-10">
        <div>
          <div className="mb-4 flex flex-wrap items-center gap-3">
            <div className="badge badge-success badge-sm gap-2 border-none px-3 py-3 text-success-content sm:badge-md sm:px-4 sm:py-4">
              <Sparkles size={14} />
              Now
            </div>
            <div className="badge badge-outline badge-sm gap-2 px-3 py-3 sm:badge-md sm:px-4 sm:py-4">
              <ShieldCheck size={14} />
              Platform System
            </div>
          </div>

          <h1 className="text-2xl font-black tracking-tight sm:text-4xl">Reports Dashboard</h1>

          <p className="mt-2 text-sm leading-6 text-base-content/65 sm:text-base sm:leading-7">
            Backend services, API layer, QA progress, title volume, and editor activity presented in a
            richer dashboard with interactive charts.
          </p>

          <div className="mt-6 flex flex-wrap gap-2 sm:gap-3">
            <div className="badge badge-primary badge-outline badge-sm px-3 py-3 uppercase sm:badge-md sm:px-4 sm:py-4">
              Signed in as {userRole}
            </div>
            <div className="badge badge-ghost badge-sm px-3 py-3 sm:badge-md sm:px-4 sm:py-4">
              {qaRate}% QA coverage
            </div>
          </div>
        </div>
      </div>
    </section>
  );
}

export default ReportsHeroSection;
