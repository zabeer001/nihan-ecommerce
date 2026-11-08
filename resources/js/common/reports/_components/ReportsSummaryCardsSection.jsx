import React from 'react';
import { SUMMARY_CARDS } from './reportConstants';
import { formatNumber } from './reportFormatters';

function ReportsSummaryCardsSection({ summary }) {
  return (
    <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4 2xl:grid-cols-7">
      {SUMMARY_CARDS.map((card) => {
        const Icon = card.icon;

        return (
          <article
            key={card.key}
            className="group rounded-2xl border border-base-300 bg-base-100 p-3 shadow-sm transition-all duration-300 hover:-translate-y-0.5 hover:shadow-lg sm:p-4"
          >
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0">
                <p className="text-[10px] font-bold uppercase tracking-[0.1em] text-base-content/55 sm:text-[11px]">
                  {card.label}
                </p>
                <h3 className="mt-2 break-words text-xl font-black leading-none tracking-tight text-base-content sm:text-2xl">
                  {formatNumber(summary[card.key])}
                </h3>
              </div>
              <div className="grid h-9 w-9 shrink-0 place-items-center rounded-2xl bg-primary/10 text-primary sm:h-10 sm:w-10">
                <Icon size={18} strokeWidth={2.2} />
              </div>
            </div>
          </article>
        );
      })}
    </section>
  );
}

export default ReportsSummaryCardsSection;
