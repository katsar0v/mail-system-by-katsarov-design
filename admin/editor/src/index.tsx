import React, { useCallback, useMemo, useState, useEffect } from 'react';
import { createRoot } from 'react-dom/client';
import { ConfigProvider } from '@arco-design/web-react';
import enUS from '@arco-design/web-react/es/locale/en-US';
import {
  EmailEditor,
  EmailEditorProvider,
  IEmailTemplate,
} from 'easy-email-editor';
import { BlockManager, BasicType, AdvancedType, JsonToMjml } from 'easy-email-core';
import { ExtensionProps, StandardLayout } from 'easy-email-extensions';
import mjml from 'mjml-browser';

// Import styles
import 'easy-email-editor/lib/style.css';
import 'easy-email-extensions/lib/style.css';
import '@arco-themes/react-easy-email-theme/css/arco.css';

// Types for WordPress integration
interface MSKDEditorConfig {
  ajaxUrl: string;
  nonce: string;
  templateId?: number;
  jsonContent?: string;
  htmlContent?: string;
  subject?: string;
  returnUrl: string;
  saveAction: string;
  strings: {
    save: string;
    saving: string;
    saved: string;
    error: string;
    exportHtml: string;
    cancel: string;
    untitledTemplate: string;
  };
}

declare global {
  interface Window {
    mskdEditorConfig: MSKDEditorConfig;
  }
}

// Default categories for the editor
const defaultCategories: ExtensionProps['categories'] = [
  {
    label: 'Content',
    active: true,
    blocks: [
      { type: AdvancedType.TEXT },
      { type: AdvancedType.IMAGE, payload: { attributes: { padding: '0px 0px 0px 0px' } } },
      { type: AdvancedType.BUTTON },
      { type: AdvancedType.SOCIAL },
      { type: AdvancedType.DIVIDER },
      { type: AdvancedType.SPACER },
      { type: AdvancedType.HERO },
      { type: AdvancedType.WRAPPER },
    ],
  },
  {
    label: 'Layout',
    active: true,
    displayType: 'column',
    blocks: [
      {
        title: '2 columns',
        payload: [
          ['50%', '50%'],
          ['33%', '67%'],
          ['67%', '33%'],
          ['25%', '75%'],
          ['75%', '25%'],
        ],
      },
      {
        title: '3 columns',
        payload: [
          ['33.33%', '33.33%', '33.33%'],
          ['25%', '25%', '50%'],
          ['50%', '25%', '25%'],
        ],
      },
      {
        title: '4 columns',
        payload: [['25%', '25%', '25%', '25%']],
      },
    ],
  },
];

// Merge tags for subscriber placeholders
const mergeTags = {
  subscriber: {
    name: 'Subscriber',
    mergeTags: {
      first_name: {
        name: 'First Name',
        value: '{first_name}',
      },
      last_name: {
        name: 'Last Name',
        value: '{last_name}',
      },
      email: {
        name: 'Email',
        value: '{email}',
      },
    },
  },
  links: {
    name: 'Links',
    mergeTags: {
      unsubscribe: {
        name: 'Unsubscribe Link',
        value: '{unsubscribe_link}',
      },
      unsubscribe_url: {
        name: 'Unsubscribe URL',
        value: '{unsubscribe_url}',
      },
    },
  },
};

function VisualEmailEditor() {
  const config = window.mskdEditorConfig;
  const [isSaving, setIsSaving] = useState(false);
  const [saveMessage, setSaveMessage] = useState('');

  // Parse initial content from WordPress
  const initialValues: IEmailTemplate = useMemo(() => {
    if (config.jsonContent) {
      try {
        const parsed = JSON.parse(config.jsonContent);
        return {
          subject: config.subject || config.strings.untitledTemplate,
          subTitle: '',
          content: parsed,
        };
      } catch (e) {
        console.error('Failed to parse JSON content:', e);
      }
    }

    // Create default template
    return {
      subject: config.subject || config.strings.untitledTemplate,
      subTitle: '',
      content: BlockManager.getBlockByType(BasicType.PAGE)!.create({
        children: [
          BlockManager.getBlockByType(AdvancedType.WRAPPER)!.create({
            children: [
              BlockManager.getBlockByType(AdvancedType.SECTION)!.create({
                children: [
                  BlockManager.getBlockByType(AdvancedType.COLUMN)!.create({
                    children: [
                      BlockManager.getBlockByType(AdvancedType.TEXT)!.create({
                        data: {
                          value: {
                            content: '<p>Start building your email template...</p>',
                          },
                        },
                      }),
                    ],
                  }),
                ],
              }),
            ],
          }),
        ],
      }),
    };
  }, [config.jsonContent, config.subject, config.strings.untitledTemplate]);

  // Image upload handler
  const onUploadImage = useCallback(async (blob: Blob): Promise<string> => {
    // Create FormData for WordPress media upload
    const formData = new FormData();
    formData.append('action', 'mskd_upload_editor_image');
    formData.append('nonce', config.nonce);
    formData.append('file', blob, 'image.png');

    try {
      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        body: formData,
      });
      const result = await response.json();
      
      if (result.success && result.data.url) {
        return result.data.url;
      }
      throw new Error(result.data?.message || 'Upload failed');
    } catch (error) {
      console.error('Image upload failed:', error);
      // Return a placeholder or blob URL as fallback
      return URL.createObjectURL(blob);
    }
  }, [config.ajaxUrl, config.nonce]);

  // Save handler
  const handleSave = useCallback(async (values: IEmailTemplate) => {
    setIsSaving(true);
    setSaveMessage('');

    try {
      // Convert JSON to MJML
      const mjmlString = JsonToMjml({
        data: values.content,
        mode: 'production',
        context: values.content,
      });

      // Convert MJML to HTML
      const htmlResult = mjml(mjmlString, {
        validationLevel: 'soft',
      });

      // Prepare data for WordPress
      const formData = new FormData();
      formData.append('action', config.saveAction);
      formData.append('nonce', config.nonce);
      formData.append('template_id', String(config.templateId || 0));
      formData.append('subject', values.subject);
      formData.append('content', htmlResult.html);
      formData.append('json_content', JSON.stringify(values.content));

      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        body: formData,
      });

      const result = await response.json();

      if (result.success) {
        setSaveMessage(config.strings.saved);
        // Redirect back to templates list after save if specified
        if (config.returnUrl && result.data?.redirect) {
          window.location.href = config.returnUrl;
        }
      } else {
        throw new Error(result.data?.message || config.strings.error);
      }
    } catch (error) {
      console.error('Save failed:', error);
      setSaveMessage(config.strings.error);
    } finally {
      setIsSaving(false);
    }
  }, [config]);

  // Export HTML handler
  const exportHtml = useCallback((values: IEmailTemplate) => {
    const mjmlString = JsonToMjml({
      data: values.content,
      mode: 'production',
      context: values.content,
    });

    const htmlResult = mjml(mjmlString, {
      validationLevel: 'soft',
    });

    // Copy to clipboard
    navigator.clipboard.writeText(htmlResult.html).then(() => {
      alert('HTML copied to clipboard!');
    });
  }, []);

  // Cancel handler
  const handleCancel = useCallback(() => {
    if (config.returnUrl) {
      window.location.href = config.returnUrl;
    }
  }, [config.returnUrl]);

  return (
    <ConfigProvider locale={enUS}>
      <div style={{ height: '100vh' }}>
        <EmailEditorProvider
          data={initialValues}
          height="calc(100vh - 60px)"
          autoComplete
          dashed={false}
          mergeTags={mergeTags}
          onUploadImage={onUploadImage}
        >
          {({ values }, { submit }) => {
            return (
              <>
                {/* Header toolbar */}
                <div
                  style={{
                    display: 'flex',
                    alignItems: 'center',
                    justifyContent: 'space-between',
                    padding: '10px 20px',
                    backgroundColor: '#1d2327',
                    color: '#fff',
                    height: '60px',
                    boxSizing: 'border-box',
                  }}
                >
                  <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    <button
                      onClick={handleCancel}
                      style={{
                        padding: '8px 16px',
                        backgroundColor: '#50575e',
                        color: '#fff',
                        border: 'none',
                        borderRadius: '4px',
                        cursor: 'pointer',
                      }}
                    >
                      ← {config.strings.cancel}
                    </button>
                    <span style={{ fontSize: '14px', fontWeight: 500 }}>
                      {values.subject || config.strings.untitledTemplate}
                    </span>
                  </div>
                  <div style={{ display: 'flex', alignItems: 'center', gap: '10px' }}>
                    {saveMessage && (
                      <span style={{ fontSize: '12px', color: saveMessage === config.strings.saved ? '#46b450' : '#dc3232' }}>
                        {saveMessage}
                      </span>
                    )}
                    <button
                      onClick={() => exportHtml(values)}
                      style={{
                        padding: '8px 16px',
                        backgroundColor: '#50575e',
                        color: '#fff',
                        border: 'none',
                        borderRadius: '4px',
                        cursor: 'pointer',
                      }}
                    >
                      {config.strings.exportHtml}
                    </button>
                    <button
                      onClick={() => handleSave(values)}
                      disabled={isSaving}
                      style={{
                        padding: '8px 20px',
                        backgroundColor: '#2271b1',
                        color: '#fff',
                        border: 'none',
                        borderRadius: '4px',
                        cursor: isSaving ? 'not-allowed' : 'pointer',
                        opacity: isSaving ? 0.7 : 1,
                      }}
                    >
                      {isSaving ? config.strings.saving : config.strings.save}
                    </button>
                  </div>
                </div>

                {/* Editor */}
                <StandardLayout
                  categories={defaultCategories}
                  showSourceCode={true}
                  mjmlReadOnly={false}
                  showBlockLayer={false}
                >
                  <EmailEditor />
                </StandardLayout>
              </>
            );
          }}
        </EmailEditorProvider>
      </div>
    </ConfigProvider>
  );
}

// Initialize the editor when DOM is ready
function initEditor() {
  const container = document.getElementById('mskd-visual-editor-root');
  if (container) {
    const root = createRoot(container);
    root.render(<VisualEmailEditor />);
  }
}

// Export for WordPress to call
(window as any).MSKDVisualEditor = {
  init: initEditor,
};

// Auto-init if container exists
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initEditor);
} else {
  initEditor();
}

export default VisualEmailEditor;
