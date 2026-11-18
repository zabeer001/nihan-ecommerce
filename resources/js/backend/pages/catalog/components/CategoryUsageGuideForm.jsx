import { Field, input } from '../catalogUi';

export default function CategoryUsageGuideForm({ value, onChange }) {
  const guide = { heading: '', button_text: '', button_url: '', steps: [], ...value };
  const steps = guide.steps || [];
  const change = (key, next) => onChange({ ...guide, [key]: next });
  const changeStep = (index, key, value) => change('steps', steps.map((step, i) => i === index ? { ...step, [key]: value } : step));
  function move(index, direction) {
    const next = [...steps];
    [next[index], next[index + direction]] = [next[index + direction], next[index]];
    change('steps', next);
  }
  return <div className="md:col-span-2 space-y-4 border-t border-base-300 pt-5">
    <div className="flex flex-wrap items-center justify-between gap-3"><h2 className="text-lg font-semibold">How to use</h2><button type="button" className="btn btn-sm" disabled={steps.length >= 12} onClick={() => change('steps', [...steps, { title: '', description: '' }])}>Add step</button></div>
    <p className="text-sm opacity-70">This category’s guide appears on the homepage. Remove all steps to hide it.</p>
    <div className="grid gap-4 md:grid-cols-2">
      <Field label="Section heading"><input className={input} required={steps.length > 0} maxLength={255} value={guide.heading || ''} onChange={e => change('heading', e.target.value)} /></Field>
      <Field label="Button text (optional)"><input className={input} maxLength={80} required={Boolean(guide.button_url)} value={guide.button_text || ''} onChange={e => change('button_text', e.target.value)} /></Field>
      <Field label="Button link (optional)"><input className={input} maxLength={2048} required={Boolean(guide.button_text)} placeholder="/products" value={guide.button_url || ''} onChange={e => change('button_url', e.target.value)} /></Field>
    </div>
    {steps.map((step, index) => <fieldset key={index} className="rounded-box border border-base-300 p-4 space-y-3">
      <legend className="px-2 font-semibold">Step {index + 1}</legend>
      <Field label="Title"><input className={input} required maxLength={150} value={step.title || ''} onChange={e => changeStep(index, 'title', e.target.value)} /></Field>
      <Field label="Description"><textarea className="textarea textarea-bordered w-full" required maxLength={2000} rows={3} value={step.description || ''} onChange={e => changeStep(index, 'description', e.target.value)} /></Field>
      <div className="flex flex-wrap gap-2"><button type="button" className="btn btn-sm" disabled={index === 0} onClick={() => move(index, -1)}>Move up</button><button type="button" className="btn btn-sm" disabled={index === steps.length - 1} onClick={() => move(index, 1)}>Move down</button><button type="button" className="btn btn-sm btn-error btn-outline" onClick={() => change('steps', steps.filter((_, i) => i !== index))}>Remove step</button></div>
    </fieldset>)}
  </div>;
}
