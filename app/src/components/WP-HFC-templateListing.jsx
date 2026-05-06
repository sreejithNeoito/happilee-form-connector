import React, { useState } from "react";
import WPHFC_PreviewModal from "./WP-HFC-previewModal";

export const ITEMS_PER_PAGE = 10;

const WPHFC_TemplateListing = ({
  templates,
  isLoadingTemplates,
  selectedTemplate,
  onSelect,
  currentPage,
}) => {
  const [previewTemplate, setPreviewTemplate] = useState(null);

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
                        e.stopPropagation(); // prevent row select
                        setPreviewTemplate(template);
                      }}
                      className="wphfc-flex wphfc-items-center wphfc-gap-1 wphfc-text-xs wphfc-font-medium wphfc-text-blue-600 wphfc-bg-blue-50 wphfc-border wphfc-border-blue-200 wphfc-rounded wphfc-px-2 wphfc-py-1 hover:wphfc-bg-blue-100 wphfc-cursor-pointer wphfc-transition-colors wphfc-border-none">
                      {/* Eye Icon */}
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

      {/* Preview Modal */}
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
