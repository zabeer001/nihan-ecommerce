import React, { useEffect, useRef } from 'react';
import { Bold, Eraser, Italic, List, ListOrdered, Underline } from 'lucide-react';

function RichTextEditor({ name, value, onChange, minHeight = 'min-h-32' }) {
    const editorRef = useRef(null);

    useEffect(() => {
        if (editorRef.current && editorRef.current.innerHTML !== (value || '')) {
            editorRef.current.innerHTML = value || '';
        }
    }, [value]);

    const emitChange = () => {
        onChange({
            target: {
                name,
                value: editorRef.current?.innerHTML || '',
            },
        });
    };

    const runCommand = (command) => {
        editorRef.current?.focus();
        document.execCommand(command, false, null);
        emitChange();
    };

    return (
        <div className="rounded-lg border border-base-300 bg-base-100">
            <div className="flex flex-wrap gap-1 border-b border-base-300 p-2">
                <button type="button" className="btn btn-ghost btn-xs" title="Bold" onClick={() => runCommand('bold')}>
                    <Bold className="size-3.5" />
                </button>
                <button type="button" className="btn btn-ghost btn-xs" title="Italic" onClick={() => runCommand('italic')}>
                    <Italic className="size-3.5" />
                </button>
                <button type="button" className="btn btn-ghost btn-xs" title="Underline" onClick={() => runCommand('underline')}>
                    <Underline className="size-3.5" />
                </button>
                <button type="button" className="btn btn-ghost btn-xs" title="Bullet list" onClick={() => runCommand('insertUnorderedList')}>
                    <List className="size-3.5" />
                </button>
                <button type="button" className="btn btn-ghost btn-xs" title="Numbered list" onClick={() => runCommand('insertOrderedList')}>
                    <ListOrdered className="size-3.5" />
                </button>
                <button type="button" className="btn btn-ghost btn-xs" title="Clear formatting" onClick={() => runCommand('removeFormat')}>
                    <Eraser className="size-3.5" />
                </button>
            </div>
            <div
                ref={editorRef}
                className={`prose prose-sm max-w-none p-3 outline-none ${minHeight}`}
                contentEditable
                role="textbox"
                aria-multiline="true"
                onInput={emitChange}
                onBlur={emitChange}
                suppressContentEditableWarning
            />
        </div>
    );
}

export default RichTextEditor;
