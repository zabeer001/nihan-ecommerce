import Select from 'react-select';

export default function CategorySelect({ categories, selectedIds, onChange, disabled = false }) {
  const options = categories.map(category => ({ value: category.id, label: category.name }));
  const selected = new Set(selectedIds.map(Number));

  return <Select
    inputId="product-categories"
    aria-label="Categories"
    name="category_ids"
    isMulti
    isSearchable
    isClearable
    required
    unstyled
    isDisabled={disabled}
    closeMenuOnSelect={false}
    options={options}
    value={options.filter(option => selected.has(option.value))}
    onChange={items => onChange(items.map(item => item.value))}
    placeholder="Search and select categories…"
    noOptionsMessage={() => 'No matching categories'}
    classNames={{
      container: () => 'w-full text-sm',
      control: ({ isFocused, isDisabled }) => `min-h-12 rounded-lg border bg-base-100 text-base-content ${isFocused ? 'border-primary ring-2 ring-primary/20' : 'border-base-content/20'} ${isDisabled ? 'opacity-50' : ''}`,
      valueContainer: () => 'flex flex-wrap gap-1.5 px-3 py-2',
      placeholder: () => 'text-base-content/50',
      input: () => 'text-base-content',
      multiValue: () => 'inline-flex items-center rounded-md border border-primary/20 bg-primary/10 text-base-content',
      multiValueLabel: () => 'px-2 py-1',
      multiValueRemove: () => 'cursor-pointer rounded-r-md px-1.5 hover:bg-error hover:text-error-content',
      indicatorsContainer: () => 'gap-1 px-2 text-base-content/60',
      clearIndicator: () => 'cursor-pointer p-1 hover:text-error',
      dropdownIndicator: () => 'cursor-pointer p-1',
      indicatorSeparator: () => 'mx-1 self-stretch bg-base-300 w-px',
      menu: () => 'mt-2 overflow-hidden rounded-lg border border-base-300 bg-base-100 text-base-content shadow-xl z-50',
      menuList: () => 'max-h-60 p-1',
      option: ({ isFocused }) => `cursor-pointer rounded-md px-3 py-2 ${isFocused ? 'bg-base-200' : ''}`,
      noOptionsMessage: () => 'p-3 text-base-content/60',
    }}
  />;
}
