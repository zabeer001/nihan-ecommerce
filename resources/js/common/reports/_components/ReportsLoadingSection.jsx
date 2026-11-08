import React from 'react';

function ReportsLoadingSection() {
  return (
    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      {[...Array(6)].map((_, index) => (
        <div key={index} className="rounded-3xl border border-base-300 bg-base-100 p-6 shadow-sm">
          <div className="skeleton mb-3 h-5 w-28"></div>
          <div className="skeleton mb-4 h-10 w-32"></div>
          <div className="skeleton h-3 w-full"></div>
          <div className="skeleton mt-2 h-3 w-4/5"></div>
        </div>
      ))}
    </section>
  );
}

export default ReportsLoadingSection;
