import React, { useState, useEffect } from "react";

const WPHFC_TemplateSettings = ({
  selectedTemplate,
  formFields,
  formType,
  savedParamMappings,
  onParamMappingsChange,
}) => {
  const params = selectedTemplate?.total_params || [];
  const [paramMappings, setParamMappings] = useState(savedParamMappings || {});
  const [openDropdown, setOpenDropdown] = useState(null);

  useEffect(() => {
    if (savedParamMappings && Object.keys(savedParamMappings).length > 0) {
      setParamMappings(savedParamMappings);
    }
  }, [savedParamMappings]);

  const handleMap = (param, formField) => {
    let fieldValue = null;
    if (formField) {
      switch (formType) {
        case "wpforms":
          fieldValue = formField.name;
          break;
        case "cf7":
          fieldValue = formField.name;
          break;
        case "ninja_forms":
          fieldValue = formField.name;
          break;
        case "forminator":
          fieldValue = formField.id || formField.name;
          break;
        default:
          fieldValue = formField.name || formField.id;
      }
    }

    const newMappings = { ...paramMappings, [param]: fieldValue };

    setParamMappings(newMappings);
    onParamMappingsChange?.(newMappings);
    setOpenDropdown(null);
  };

  const getLivePreview = () => {
    let body = selectedTemplate?.body || "";
    params.forEach((param) => {
      const mappedValue = paramMappings[param];
      const mappedLabel = mappedValue
        ? formFields.find((f) => (f.name || f.id) === mappedValue)?.label ||
          mappedValue
        : `{{${param}}}`;
      body = body.replaceAll(`{{${param}}}`, mappedLabel);
    });
    return body;
  };

  const now = new Date().toLocaleTimeString([], {
    hour: "2-digit",
    minute: "2-digit",
  });

  return (
    <div
      className="wphfc-flex wphfc-gap-4 wphfc-mb-6"
      style={{ minHeight: "460px" }}>
      {/* ─── LEFT: Setup Panel ─── */}
      <div
        className="wphfc-flex wphfc-flex-col wphfc-gap-4"
        style={{ flex: "1" }}>
        {/* Template Info */}
        <div className="wphfc-p-3 wphfc-border wphfc-border-gray-200 wphfc-rounded-md wphfc-bg-gray-50">
          <p className="wphfc-text-sm wphfc-text-gray-800 wphfc-m-0">
            <b>Template:</b> {selectedTemplate.template_name}
          </p>
          <p className="wphfc-text-sm wphfc-text-gray-800 wphfc-mt-1 wphfc-m-0">
            <b>Category:</b> {selectedTemplate.category}
          </p>
          <p className="wphfc-text-sm wphfc-text-gray-800 wphfc-mt-1 wphfc-m-0">
            <b>Created:</b>{" "}
            {new Date(selectedTemplate.createdAt).toLocaleDateString("en-IN", {
              day: "2-digit",
              month: "short",
              year: "numeric",
            })}
          </p>
        </div>

        {/* Param Mapping */}
        {params.length > 0 ? (
          <div
            className="wphfc-bg-white wphfc-border wphfc-border-gray-200 wphfc-rounded-md wphfc-p-4"
            style={{ boxShadow: "0 1px 4px rgba(0,0,0,0.06)" }}>
            <h3 className="wphfc-text-lg wphfc-font-semibold wphfc-mb-3 wphfc-text-primary wphfc-px-3">
              Map Parameters to Form Fields
            </h3>
            <div className="wphfc-space-y-3 wphfc-p-3">
              {params.map((param) => (
                <div
                  key={param}
                  className="wphfc-flex wphfc-items-center wphfc-gap-3">
                  {/* Param Badge */}
                  <label className="wphfc-flex-shrink-0 wphfc-w-[30%]">
                    <span
                      className="wphfc-inline-block wphfc-text-xs wphfc-px-2 wphfc-py-1 wphfc-rounded wphfc-font-mono"
                      style={{
                        background: "#fef9c3",
                        color: "#854d0e",
                        border: "1px solid #fde047",
                      }}>
                      {`{{${param}}}`}
                    </span>
                  </label>

                  {/* Dropdown */}
                  <div className="wphfc-flex-1 wphfc-relative wphfc-w-[70%]">
                    <button
                      type="button"
                      className="wphfc-select wphfc-flex wphfc-justify-between wphfc-items-center wphfc-w-full wphfc-px-3 wphfc-py-2 wphfc-bg-white wphfc-border wphfc-border-gray-300 wphfc-rounded-md wphfc-text-left wphfc-text-sm wphfc-cursor-pointer hover:wphfc-border-gray-400"
                      onClick={() =>
                        setOpenDropdown(openDropdown === param ? null : param)
                      }>
                      <span
                        className={
                          paramMappings[param]
                            ? "wphfc-text-gray-900"
                            : "wphfc-text-gray-400"
                        }>
                        {paramMappings[param]
                          ? formFields.find(
                              (f) => (f.name || f.id) === paramMappings[param],
                            )?.label || paramMappings[param]
                          : "Select a form field"}
                      </span>
                      <svg
                        width="16"
                        height="16"
                        viewBox="0 0 24 24"
                        fill="none"
                        className={`wphfc-transition-transform ${openDropdown === param ? "wphfc-rotate-180" : ""}`}>
                        <path
                          d="M6 9L12 15L18 9"
                          stroke="currentColor"
                          strokeWidth="1.5"
                          strokeLinecap="round"
                          strokeLinejoin="round"
                        />
                      </svg>
                    </button>

                    {openDropdown === param && (
                      <div className="wphfc-absolute wphfc-z-10 wphfc-w-full wphfc-mt-1 wphfc-bg-white wphfc-border wphfc-border-gray-300 wphfc-rounded-md wphfc-shadow-xl wphfc-max-h-48 wphfc-overflow-y-auto">
                        <ul className="wphfc-py-1">
                          <li>
                            <button
                              type="button"
                              className="wphfc-w-full wphfc-px-3 wphfc-py-2 wphfc-text-left wphfc-text-sm wphfc-text-gray-400 hover:wphfc-bg-gray-100 wphfc-cursor-pointer wphfc-border-none wphfc-bg-transparent"
                              onClick={() => handleMap(param, null)}>
                              -- None --
                            </button>
                          </li>
                          {formFields.length > 0 ? (
                            formFields.map((formField) => (
                              <li key={formField.id || formField.name}>
                                <button
                                  type="button"
                                  className={`wphfc-w-full wphfc-px-3 wphfc-py-2 wphfc-text-left wphfc-text-sm hover:wphfc-bg-blue-50 wphfc-cursor-pointer wphfc-border-none wphfc-bg-transparent ${
                                    paramMappings[param] ===
                                    (formField.name || formField.id)
                                      ? "wphfc-bg-blue-100 wphfc-text-[#0B3966] wphfc-font-medium"
                                      : "wphfc-text-gray-700"
                                  }`}
                                  onClick={() => handleMap(param, formField)}>
                                  {formField.label || formField.name}
                                </button>
                              </li>
                            ))
                          ) : (
                            <li className="wphfc-px-3 wphfc-py-2 wphfc-text-sm wphfc-text-gray-400 wphfc-italic">
                              No fields available
                            </li>
                          )}
                        </ul>
                      </div>
                    )}
                  </div>
                </div>
              ))}
            </div>
          </div>
        ) : (
          <div className="wphfc-p-3 wphfc-bg-gray-50 wphfc-border wphfc-border-gray-200 wphfc-rounded-md">
            <p className="wphfc-text-sm wphfc-text-gray-500 wphfc-m-0 wphfc-italic">
              This template has no dynamic parameters.
            </p>
          </div>
        )}
      </div>

      {/* ─── RIGHT: WhatsApp Preview ─── */}
      <div className="wphfc-w-[300px] wphfc-shrink-0 wphfc-border wphfc-border-gray-200 wphfc-shadow-[0_2px_8px_rgba(0,0,0,0.08)] wphfc-flex wphfc-flex-col wphfc-rounded-lg wphfc-overflow-hidden">
        {/* WhatsApp Top Bar */}
        <div
          className="wphfc-flex wphfc-items-center wphfc-gap-2 wphfc-px-3 wphfc-py-2"
          style={{ background: "#075e54" }}>
          {/* Avatar */}
          <div className="wphfc-w-[34px] wphfc-h-[34px] wphfc-bg-[#25d366] wphfc-shrink-0 wphfc-flex wphfc-items-center wphfc-justify-center wphfc-rounded-full">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="white">
              <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" />
            </svg>
          </div>
          <div>
            <p className="wphfc-text-white wphfc-text-sm wphfc-font-semibold wphfc-m-0">
              {"User Name"}
            </p>
            <p className="wphfc-text-white wphfc-text-xs wphfc-m-0">online</p>
          </div>
          {/* Icons */}
          <div
            className="wphfc-flex wphfc-gap-3 wphfc-items-center"
            style={{ marginLeft: "auto" }}>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path
                d="M15 10l4.553-2.07A1 1 0 0121 8.87v6.26a1 1 0 01-1.447.9L15 14M3 8a2 2 0 012-2h10a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2V8z"
                stroke="white"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <circle cx="12" cy="5" r="1.5" fill="white" />
              <circle cx="12" cy="12" r="1.5" fill="white" />
              <circle cx="12" cy="19" r="1.5" fill="white" />
            </svg>
          </div>
        </div>

        {/* Chat Background */}
        <div
          className="wphfc-flex-1 wphfc-flex wphfc-flex-col wphfc-px-3 wphfc-py-4 wphfc-overflow-y-auto"
          style={{
            background: "#efeae2",
            backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23d4cfc7' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`,
            minHeight: "280px",
          }}>
          {/* TODAY Badge */}
          <div className="wphfc-flex wphfc-justify-center wphfc-mb-3">
            <span className="wphfc-bg-white/75 wphfc-text-gray-600 wphfc-text-xs wphfc-px-3 wphfc-py-1 wphfc-rounded-full">
              TODAY
            </span>
          </div>

          {/* Message Bubble */}
          <div className="wphfc-flex wphfc-justify-end">
            <div
              className="wphfc-relative"
              style={{
                background: "#dcf8c6",
                borderRadius: "8px 0px 8px 8px",
                padding: "8px 10px 20px 10px",
                maxWidth: "85%",
                minWidth: "120px",
                boxShadow: "0 1px 2px rgba(0,0,0,0.15)",
                position: "relative",
              }}>
              {/* Tail */}
              <div
                style={{
                  position: "absolute",
                  top: 0,
                  right: "-8px",
                  width: 0,
                  height: 0,
                  borderLeft: "8px solid #dcf8c6",
                  borderBottom: "8px solid transparent",
                }}
              />
              {/* Message Text */}
              <p
                className="wphfc-m-0"
                style={{
                  fontSize: "12px",
                  color: "#111",
                  whiteSpace: "pre-wrap",
                  lineHeight: "1.5",
                  wordBreak: "break-word",
                }}>
                {getLivePreview()}
              </p>
              {/* Time + Tick */}
              <div
                className="wphfc-flex wphfc-items-center wphfc-gap-1"
                style={{
                  position: "absolute",
                  bottom: "5px",
                  right: "8px",
                }}>
                <span style={{ fontSize: "10px", color: "#888" }}>{now}</span>
                {/* Double blue tick */}
                <svg width="16" height="10" viewBox="0 0 16 10" fill="none">
                  <path
                    d="M1 5l3.5 3.5L11 1"
                    stroke="#53bdeb"
                    strokeWidth="1.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                  <path
                    d="M5 5l3.5 3.5L15 1"
                    stroke="#53bdeb"
                    strokeWidth="1.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
              </div>
            </div>
          </div>
        </div>

        {/* WhatsApp Input Bar */}
        <div
          className="wphfc-flex wphfc-items-center wphfc-gap-2 wphfc-px-2 wphfc-py-2"
          style={{ background: "#f0f0f0", borderTop: "1px solid #ddd" }}>
          <div
            className="wphfc-flex-1 wphfc-rounded-full wphfc-px-3 wphfc-py-1"
            style={{
              background: "white",
              fontSize: "12px",
              color: "#aaa",
              border: "none",
            }}>
            Type a message
          </div>
          <div
            className="wphfc-flex wphfc-items-center wphfc-justify-center wphfc-rounded-full"
            style={{
              width: "32px",
              height: "32px",
              background: "#25d366",
              flexShrink: 0,
            }}>
            <svg width="16" height="16" viewBox="0 0 24 24" fill="white">
              <path d="M2 21l21-9L2 3v7l15 2-15 2v7z" />
            </svg>
          </div>
        </div>
      </div>
    </div>
  );
};

export default WPHFC_TemplateSettings;
