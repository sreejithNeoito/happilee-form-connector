import React, { useState, useEffect, useRef } from "react";
import WPHFC_PreviewModal from "./WP-HFC-previewModal";

export const ITEMS_PER_PAGE = 10;

const WPHFC_TemplateListing = ({
  templates,
  isLoadingTemplates,
  selectedTemplate,
  onSelect,
  currentPage,
  selectedFormId,
  formType,
}) => {
  const [previewTemplate, setPreviewTemplate] = useState(null);
  const selectedRowRef = useRef(null);
  const hasAutoSelected = useRef(false);

  useEffect(() => {
    // Only run when templates are loaded and we haven't auto-selected yet
    if (templates.length === 0 || hasAutoSelected.current) return;

    const fetchTemplateSettings = async () => {
      try {
        const response = await fetch(
          `${happileeConnect.rest_url}fetch-template-settings?form_id=${selectedFormId}&form_type=${formType}`,
          {
            method: "GET",
            headers: {
              "Content-Type": "application/json",
              "X-WP-Nonce": happileeConnect.happfoco_nonce,
            },
            credentials: "same-origin",
          },
        );

        const data = await response.json();

        if (response.ok && data.success && data.template_settings) {
          const saved = data.template_settings;

          // Find the full template object from the loaded templates list
          const matchedTemplate = templates.find(
            (t) => t.template_id === saved.template_id,
          );

          if (matchedTemplate) {
            onSelect(matchedTemplate);
            hasAutoSelected.current = true;
          }
        }
      } catch (error) {
        console.error("Error fetching saved template settings:", error);
      }
    };

    fetchTemplateSettings();
  }, [templates]);

  // Auto-scroll to selected row
  useEffect(() => {
    if (selectedRowRef.current) {
      selectedRowRef.current.scrollIntoView({
        block: "center",
        behavior: "smooth",
      });
    }
  }, [selectedTemplate]);

  if (isLoadingTemplates) {
    return (
      <p className="wphfc-text-sm wphfc-text-gray-500 wphfc-text-center wphfc-py-4">
        Loading templates...
      </p>
    );
  }

  if (templates.length === 0) {
    return (
      <p className="wphfc-text-sm wphfc-text-gray-500 wphfc-text-center wphfc-py-4">
        No templates found
      </p>
    );
  }

  const approvedTemplates = templates.filter(
    (template) => template.status === "approved",
  );

  const startIndex = (currentPage - 1) * ITEMS_PER_PAGE;
  const paginatedTemplates = approvedTemplates.slice(
    startIndex,
    startIndex + ITEMS_PER_PAGE,
  );

  return (
    <>
      <div className="wphfc-mb-4 wphfc-max-h-64 wphfc-overflow-y-auto">
        <table className="wphfc-w-full wphfc-border-collapse wphfc-text-sm">
          <thead className="wphfc-sticky wphfc-top-0 wphfc-z-10">
            <tr className="wphfc-bg-gray-100">
              <th className="wphfc-px-3 wphfc-py-2 wphfc-text-left wphfc-font-semibold wphfc-text-gray-700 wphfc-w-[45%]">
                Template Name
              </th>
              <th className="wphfc-px-3 wphfc-py-2 wphfc-text-left wphfc-font-semibold wphfc-text-gray-700 wphfc-w-[20%]">
                Language
              </th>
              <th className="wphfc-px-3 wphfc-py-2 wphfc-text-left wphfc-font-semibold wphfc-text-gray-700 wphfc-w-[20%]">
                Category
              </th>
              <th className="wphfc-px-3 wphfc-py-2 wphfc-text-left wphfc-font-semibold wphfc-text-gray-700 wphfc-w-[15%]">
                Preview
              </th>
            </tr>
          </thead>
          <tbody>
            {paginatedTemplates.map((template) => {
              const isSelected =
                selectedTemplate?.template_id === template.template_id;
              return (
                <tr
                  key={template.template_id}
                  ref={isSelected ? selectedRowRef : null}
                  onClick={() => onSelect(template)}
                  className={`wphfc-border-b wphfc-border-gray-200 wphfc-cursor-pointer wphfc-transition-colors ${
                    isSelected ? "wphfc-bg-blue-100" : "hover:wphfc-bg-blue-50"
                  }`}>
                  <td className="wphfc-px-3 wphfc-py-2 wphfc-font-medium wphfc-text-gray-800 wphfc-break-words">
                    {isSelected && (
                      <span className="wphfc-text-blue-600 wphfc-mr-2">✓</span>
                    )}
                    {template.template_name}
                  </td>
                  <td className="wphfc-px-3 wphfc-py-2 wphfc-text-gray-600 wphfc-break-words">
                    {template.language}
                  </td>
                  <td className="wphfc-px-3 wphfc-py-2 wphfc-text-gray-600 wphfc-break-words">
                    {template.category}
                  </td>
                  <td className="wphfc-px-3 wphfc-py-2">
                    <button
                      type="button"
                      onClick={(e) => {
                        e.stopPropagation();
                        setPreviewTemplate(template);
                      }}
                      className="wphfc-flex wphfc-items-center wphfc-gap-1 wphfc-text-xs wphfc-font-medium wphfc-text-blue-600 wphfc-bg-blue-50 wphfc-border wphfc-border-blue-200 wphfc-rounded wphfc-px-2 wphfc-py-1 hover:wphfc-bg-blue-100 wphfc-cursor-pointer wphfc-transition-colors wphfc-border-none">
                      <svg
                        width="13"
                        height="13"
                        viewBox="0 0 24 24"
                        fill="none">
                        <path
                          d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                          stroke="#2563eb"
                          strokeWidth="2"
                          strokeLinecap="round"
                          strokeLinejoin="round"
                        />
                        <circle
                          cx="12"
                          cy="12"
                          r="3"
                          stroke="#2563eb"
                          strokeWidth="2"
                        />
                      </svg>
                      Preview
                    </button>
                  </td>
                </tr>
              );
            })}
          </tbody>
        </table>
      </div>

      {previewTemplate && (
        <WPHFC_PreviewModal
          template={previewTemplate}
          onClose={() => setPreviewTemplate(null)}
        />
      )}
    </>
  );
};

export default WPHFC_TemplateListing;
