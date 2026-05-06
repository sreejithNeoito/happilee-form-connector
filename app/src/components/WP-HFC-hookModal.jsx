import React, { useState, useRef, useEffect } from "react";
import { createPortal } from "react-dom";
import { ToastContainer, toast } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import WPHFC_TemplateListing, {
  ITEMS_PER_PAGE,
} from "./WP-HFC-templateListing";
import WPHFC_TemplateSearch from "./WP-HFC-themeSearch";
import WPHFC_TemplateSettings from "./WP-HFC-templateSettings";

const WPHFC_HookModal = ({
  selectedFormId,
  formType,
  onClose,
  activeHook,
  formName,
  CurrentFields,
}) => {
  const [selectedHook, setSelectedHook] = useState("");
  const [error, setError] = useState("");
  const [isSaving, setIsSaving] = useState(false);
  const [formFields, setFormFields] = useState([]);
  const [fieldMappings, setFieldMappings] = useState({});
  const [openDropdown, setOpenDropdown] = useState(null);
  const [isHookDropdownOpen, setIsHookDropdownOpen] = useState(false);
  const [isClosing, setIsClosing] = useState(false);
  const [autoCountryCode, setAutoCountryCode] = useState("");
  const [isFetchingCountryCode, setIsFetchingCountryCode] = useState(false);
  const [templateModal, setTemplateModal] = useState(false);
  const [templates, setTemplates] = useState([]);
  const [isLoadingTemplates, setIsLoadingTemplates] = useState(false);
  const [selectedTemplate, setSelectedTemplate] = useState(null);
  const [templateSettings, setTemplateSettings] = useState(false);
  const [searchQuery, setSearchQuery] = useState("");
  const [currentPage, setCurrentPage] = useState(1);

  // filteredTemplates FIRST
  const filteredTemplates = templates.filter((t) => {
    const query = searchQuery.toLowerCase();
    return (
      t.template_name?.toLowerCase().includes(query) ||
      t.category?.toLowerCase().includes(query) ||
      t.language?.toLowerCase().includes(query)
    );
  });

  // approvedTemplates and totalPages AFTER filteredTemplates
  const approvedTemplates = filteredTemplates.filter(
    (t) => t.status === "approved",
  );
  const totalPages = Math.ceil(approvedTemplates.length / ITEMS_PER_PAGE);

  // Reset page when search changes
  useEffect(() => {
    setCurrentPage(1);
  }, [searchQuery]);

  const hookDropdownRef = useRef(null);
  const fieldDropdownRefs = useRef({});

  const fieldLabelToKey = {
    "First Name": "first_name",
    "Last Name": "last_name",
    Mobile: "phone_number",
    "Country Code": "country_code",
    Birthday: "birthday",
    Tags: "tags",
  };

  const fieldKeyToLabel = Object.fromEntries(
    Object.entries(fieldLabelToKey).map(([label, key]) => [key, label]),
  );

  const notify = (message, type = "success") => {
    toast[type](message, {
      position: "top-right",
      autoClose: 3000,
      hideProgressBar: false,
      closeOnClick: true,
      pauseOnHover: true,
      draggable: true,
    });
  };

  useEffect(() => {
    const handleClickOutside = (event) => {
      if (
        hookDropdownRef.current &&
        !hookDropdownRef.current.contains(event.target)
      ) {
        setIsHookDropdownOpen(false);
      }
      if (openDropdown) {
        const currentRef = fieldDropdownRefs.current[openDropdown];
        if (currentRef && !currentRef.contains(event.target)) {
          setOpenDropdown(null);
        }
      }
    };
    document.addEventListener("mousedown", handleClickOutside);
    return () => document.removeEventListener("mousedown", handleClickOutside);
  }, [isHookDropdownOpen, openDropdown]);

  useEffect(() => {
    getFormFields(selectedFormId, formType);
  }, [selectedFormId, formType]);

  useEffect(() => {
    const formKey = String(selectedFormId);
    const savedFields = CurrentFields[formKey];
    if (savedFields) {
      try {
        const parsedFields =
          typeof savedFields === "string"
            ? JSON.parse(savedFields)
            : savedFields;
        if (parsedFields && typeof parsedFields === "object") {
          const labelKeyedMappings = {};
          Object.entries(parsedFields).forEach(
            ([storedKey, formFieldValue]) => {
              const displayLabel = fieldKeyToLabel[storedKey] || storedKey;
              labelKeyedMappings[displayLabel] = formFieldValue;
            },
          );
          setFieldMappings(labelKeyedMappings);
        }
      } catch (err) {
        console.error("Error parsing saved fields:", err);
      }
    }
  }, [CurrentFields, selectedFormId]);

  const getAvailableHooks = (formType) => {
    const hooks = {
      cf7: [
        { value: "wpcf7_mail_sent", label: "Mail Sent ( Recommended )" },
        { value: "wpcf7_before_send_mail", label: "Before Send Mail" },
        { value: "wpcf7_mail_failed", label: "Mail Failed" },
        { value: "wpcf7_submit", label: "On Submit" },
      ],
      wpforms: [
        {
          value: "wpforms_process_complete",
          label: "Process Complete ( Recommended )",
        },
        { value: "wpforms_process_before", label: "Before Process" },
        { value: "wpforms_process_after", label: "After Process" },
      ],
      ninja_forms: [
        { value: "ninja_forms_after_submission", label: "After Submission" },
      ],
      forminator: [
        {
          value: "forminator_form_after_save_entry",
          label: "After Save Entry ( Recommended )",
        },
        {
          value: "forminator_custom_form_submit_before_set_fields",
          label: "Before Set Fields",
        },
      ],
    };
    return hooks[formType] || [];
  };

  const getAvilableFields = [
    "First Name",
    "Last Name",
    "Mobile",
    "Country Code",
    "Birthday",
    "Tags",
  ];

  const getFormFields = async (formId, formType) => {
    try {
      const response = await fetch(
        `${happileeConnect.rest_url}fetch-form-fields`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": happileeConnect.happfoco_nonce,
          },
          body: JSON.stringify({
            form_id: String(formId),
            form_type: formType,
          }),
          credentials: "same-origin",
        },
      );
      const data = await response.json();
      if (response.ok && data.success) {
        setFormFields(data.fields);
      }
    } catch (error) {
      console.log(error);
    }
  };

  const getTemplate = async () => {
    setIsLoadingTemplates(true);
    try {
      const response = await fetch(
        `${happileeConnect.rest_url}fetch-template-messages`,
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
      if (response.ok && data.success) {
        setTemplates(data.template_messages.data);
      } else {
        console.error("Failed to fetch templates:", data);
      }
    } catch (error) {
      console.error("Error fetching templates:", error);
    } finally {
      setIsLoadingTemplates(false);
    }
  };

  const toggleDropdown = (fieldName) => {
    setOpenDropdown(openDropdown === fieldName ? null : fieldName);
  };

  useEffect(() => {
    const availableHooks = getAvailableHooks(formType);
    if (activeHook && availableHooks.some((h) => h.value === activeHook)) {
      setSelectedHook(activeHook);
    } else if (availableHooks.length > 0) {
      setSelectedHook(availableHooks[0].value);
    }
  }, [activeHook, formType]);

  const handleClose = (shouldRefresh) => {
    setIsClosing(true);
    setTimeout(() => {
      onClose(shouldRefresh);
    }, 300);
  };

  const handleHookSelect = (hookValue) => {
    setSelectedHook(hookValue);
    setIsHookDropdownOpen(false);
    setError("");
  };

  const getSelectedHookLabel = () => {
    const availableHooks = getAvailableHooks(formType);
    const hook = availableHooks.find((h) => h.value === selectedHook);
    return hook ? hook.label : "Please Select the Hook";
  };

  const handleFieldMapping = (happileeField, formField) => {
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
    setFieldMappings((prev) => ({ ...prev, [happileeField]: fieldValue }));
    setOpenDropdown(null);
  };

  const getMappedFieldLabel = (happileeField) => {
    const mappedValue = fieldMappings[happileeField];
    if (
      happileeField === "Country Code" &&
      (!mappedValue || mappedValue === "country-code")
    ) {
      if (isFetchingCountryCode) return "Detecting...";
      if (autoCountryCode) return `Auto: ${autoCountryCode}`;
      return "Country Code";
    }
    if (!mappedValue) return happileeField;
    const formField = formFields.find((f) => {
      switch (formType) {
        case "wpforms":
          return (f.id || f.name) === mappedValue;
        case "cf7":
          return f.name === mappedValue;
        case "ninja_forms":
          return f.name === mappedValue;
        case "forminator":
          return (f.id || f.name) === mappedValue;
        default:
          return (f.name || f.id) === mappedValue;
      }
    });
    return formField ? formField.label || formField.name : mappedValue;
  };

  const isFieldSelected = (happileeField, formField) => {
    const mappedValue = fieldMappings[happileeField];
    if (!mappedValue || mappedValue === "country-code") return false;
    switch (formType) {
      case "wpforms":
        return mappedValue === (formField.id || formField.name);
      case "cf7":
        return mappedValue === formField.name;
      case "ninja_forms":
        return mappedValue === formField.name;
      case "forminator":
        return mappedValue === (formField.id || formField.name);
      default:
        return mappedValue === (formField.name || formField.id);
    }
  };

  useEffect(() => {
    fetchCountryCodeFromIP();
  }, []);

  const fetchCountryCodeFromIP = async () => {
    setIsFetchingCountryCode(true);
    try {
      const response = await fetch("https://ipapi.co/json/");
      const data = await response.json();
      if (data && data.country_calling_code) {
        setAutoCountryCode(data.country_calling_code);
      }
    } catch (error) {
      console.error("Failed to fetch country code from IP:", error);
      setAutoCountryCode("+1");
    } finally {
      setIsFetchingCountryCode(false);
    }
  };

  /*─────────────── Save before next button logic ───────────────*/
  const saveBeforeNext = async () => {
    if (!selectedHook) {
      setError("Please select a hook");
      return false;
    }

    if (!fieldMappings["Mobile"]) {
      notify(
        "Phone field is required. Settings cannot be saved without mapping the Mobile field.",
        "error",
      );
      return false;
    }

    setIsSaving(true);
    setError("");

    const keyedFieldMappings = {};
    Object.entries(fieldMappings).forEach(([displayLabel, formFieldValue]) => {
      if (formFieldValue !== null && formFieldValue !== undefined) {
        const storageKey = fieldLabelToKey[displayLabel] || displayLabel;
        keyedFieldMappings[storageKey] = formFieldValue;
      }
    });

    if (!keyedFieldMappings["country_code"]) {
      keyedFieldMappings["country_code"] = "country-code";
    }

    try {
      const response = await fetch(
        `${happileeConnect.rest_url}save-form-settings`,
        {
          method: "POST",
          headers: {
            "Content-Type": "application/json",
            "X-WP-Nonce": happileeConnect.happfoco_nonce,
          },
          body: JSON.stringify({
            form_id: String(selectedFormId),
            form_name: formName || "",
            form_type: formType,
            active_hook: selectedHook,
            is_enabled: 1,
            form_field: keyedFieldMappings,
          }),
          credentials: "same-origin",
        },
      );

      const data = await response.json();

      if (response.ok && data.success) {
        notify("Settings saved successfully!", "success");
        return true; //success to handleNext
      } else {
        notify(data.message || "Failed to save hook", "error");
        return false;
      }
    } catch (error) {
      notify("An error occurred while saving the hook", "error");
      return false;
    } finally {
      setIsSaving(false);
    }
  };

  /*─────────────── Save Template Settings button logic ───────────────*/
  const handleTemplateSettingsSave = async () => {};

  const handleNext = async () => {
    const saved = await saveBeforeNext();
    if (!saved) return;
    await getTemplate();
    setTemplateModal(true);
  };

  const handleTemplateSelect = () => {
    setTemplateSettings(true);
  };

  const availableHooks = getAvailableHooks(formType);

  return (
    <>
      {createPortal(
        <ToastContainer
          position="top-right"
          autoClose={3000}
          hideProgressBar={false}
          newestOnTop={true}
          closeOnClick
          rtl={false}
          pauseOnFocusLoss
          draggable
          pauseOnHover
          theme="light"
          style={{ zIndex: 9999999 }}
        />,
        document.body,
      )}
      <div
        className={`wphfc-modal-backdrop wphfc-fixed wphfc-inset-0 wphfc-flex wphfc-items-center wphfc-justify-center wphfc-bg-black/50 wphfc-z-50 ${isClosing ? "wphfc-opacity-0" : "wphfc-opacity-100"}`}
        style={{ zIndex: 100000 }}
        onClick={(e) => {
          if (e.target === e.currentTarget && !isSaving) handleClose(false);
        }}>
        <div
          className={`${templateModal ? "wphfc-max-w-5xl" : "wphfc-max-w-md"} wphfc-w-full wphfc-mx-4 wphfc-bg-white wphfc-p-6 wphfc-rounded-lg wphfc-shadow-lg wphfc-transition-transform wphfc-duration-300 ${
            isClosing
              ? "wphfc-scale-95 wphfc-opacity-0"
              : "wphfc-scale-100 wphfc-opacity-100"
          }`}>
          {/* ─── Header ─── */}
          <div className="wphfc-flex wphfc-justify-between wphfc-items-center wphfc-mb-4">
            <h3 className="wphfc-text-lg wphfc-text-primary wphfc-font-semibold wphfc-m-0">
              {templateSettings
                ? `Template Settings`
                : templateModal
                  ? `Select Template for Form ID: ${selectedFormId}`
                  : `Select Hook for Form ID: ${selectedFormId}`}
            </h3>
            <div className="wphfc-flex wphfc-gap-2">
              {templateModal && !templateSettings && (
                <WPHFC_TemplateSearch
                  searchQuery={searchQuery}
                  onSearch={setSearchQuery}
                />
              )}
              <button
                className="wphfc-modal-close wphfc-p-1 wphfc-bg-transparent wphfc-border-none wphfc-cursor-pointer"
                onClick={() => handleClose(false)}
                disabled={isSaving}>
                <svg
                  className="wphfc-w-5 wphfc-h-5"
                  width="24"
                  height="24"
                  viewBox="0 0 24 24"
                  fill="none">
                  <path
                    d="M17 7L7 17M7 7L17 17"
                    stroke="currentColor"
                    strokeWidth="1.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
              </button>
            </div>
          </div>

          {/* ─── Template List ─── */}
          {templateModal && !templateSettings && (
            <WPHFC_TemplateListing
              templates={filteredTemplates}
              isLoadingTemplates={isLoadingTemplates}
              selectedTemplate={selectedTemplate}
              onSelect={setSelectedTemplate}
              currentPage={currentPage}
            />
          )}

          {/* ─── Template Settings ─── */}
          {templateSettings && selectedTemplate && (
            <WPHFC_TemplateSettings
              selectedTemplate={selectedTemplate}
              formFields={formFields}
              fieldMappings={fieldMappings}
              formType={formType}
            />
          )}

          {/* ─── Hook Dropdown ─── */}
          {!templateModal && (
            <div className="wphfc-mb-4 wphfc-relative" ref={hookDropdownRef}>
              <label className="wphfc-block wphfc-text-sm wphfc-font-medium wphfc-mb-2 wphfc-text-gray-700">
                Form Name: <strong>{formName}</strong>
              </label>
              <button
                type="button"
                className="wphfc-w-full wphfc-items-center wphfc-bg-white wphfc-flex wphfc-justify-between wphfc-modal-select wphfc-p-2 wphfc-select wphfc-rounded-md"
                onClick={() => setIsHookDropdownOpen(!isHookDropdownOpen)}
                disabled={isSaving}>
                <span
                  className={
                    selectedHook ? "wphfc-text-gray-900" : "wphfc-text-gray-500"
                  }>
                  {getSelectedHookLabel()}
                </span>
                <svg
                  width="20"
                  height="20"
                  viewBox="0 0 24 24"
                  fill="none"
                  xmlns="http://www.w3.org/2000/svg"
                  className="wphfc-transition-transform">
                  <path
                    d="M6 9L12 15L18 9"
                    stroke="currentColor"
                    strokeWidth="1.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                  />
                </svg>
              </button>
              {isHookDropdownOpen && (
                <ul className="wphfc-w-full wphfc-max-h-60 wphfc-bg-white wphfc-rounded-md wphfc-absolute wphfc-z-10 wphfc-shadow-xl wphfc-py-1 wphfc-mt-1 wphfc-border wphfc-border-gray-300 wphfc-overflow-y-auto">
                  {availableHooks.map((hook) => (
                    <li key={hook.value}>
                      <button
                        type="button"
                        className={`wphfc-w-full wphfc-px-3 wphfc-py-2 wphfc-text-left wphfc-text-sm hover:wphfc-bg-blue-50 wphfc-cursor-pointer wphfc-border-none wphfc-bg-transparent ${
                          selectedHook === hook.value
                            ? "wphfc-bg-blue-100 wphfc-text-[#0B3966] wphfc-font-medium"
                            : "wphfc-text-gray-700"
                        }`}
                        onClick={() => handleHookSelect(hook.value)}>
                        {hook.label}
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}

          {/* ─── Error ─── */}
          {!templateModal && error && (
            <div className="wphfc-mb-4 wphfc-p-3 wphfc-bg-red-50 wphfc-border wphfc-border-red-200 wphfc-rounded-md">
              <p className="wphfc-text-sm wphfc-text-red-600 wphfc-m-0">
                {error}
              </p>
            </div>
          )}

          {/* ─── Note ─── */}
          {!templateModal && (
            <div className="wphfc-mb-4 wphfc-p-3 wphfc-bg-blue-50 wphfc-border wphfc-border-blue-200 wphfc-rounded-md">
              <p className="wphfc-text-xs wphfc-text-gray-700 wphfc-m-0">
                <strong>Note:</strong> Select the appropriate hook based on when
                you want to trigger the action in the form submission process.
              </p>
            </div>
          )}

          {/* ─── Field Mapping ─── */}
          {!templateModal && (
            <div className="wphfc-field-mapping wphfc-mb-6 wphfc-pt-4 wphfc-border-t wphfc-border-gray-300">
              <h3 className="wphfc-text-base wphfc-font-semibold wphfc-mb-4 wphfc-text-gray-800">
                Happilee Field Mapping
              </h3>
              <div className="wphfc-space-y-3">
                {getAvilableFields.map((field) => (
                  <div
                    key={field}
                    className="wphfc-field-map-row wphfc-flex wphfc-items-center wphfc-gap-3">
                    <label className="wphfc-text-sm wphfc-font-medium wphfc-text-gray-700 wphfc-w-32 wphfc-flex-shrink-0">
                      {field}
                    </label>
                    <div
                      className="wphfc-field-dropdown-container wphfc-flex-1 wphfc-relative"
                      ref={(el) => (fieldDropdownRefs.current[field] = el)}>
                      <button
                        type="button"
                        className="wphfc-select wphfc-flex wphfc-justify-between wphfc-items-center wphfc-w-full wphfc-px-3 wphfc-py-2 wphfc-bg-white wphfc-border wphfc-border-gray-300 wphfc-rounded-md wphfc-text-left wphfc-text-sm wphfc-text-gray-700 hover:wphfc-border-gray-400 focus:wphfc-outline-none focus:wphfc-ring-2 focus:wphfc-ring-blue-500 wphfc-cursor-pointer"
                        onClick={() => toggleDropdown(field)}>
                        <span
                          className={
                            fieldMappings[field]
                              ? "wphfc-text-gray-900"
                              : "wphfc-text-gray-500"
                          }>
                          {getMappedFieldLabel(field)}
                        </span>
                        <svg
                          width="20"
                          height="20"
                          viewBox="0 0 24 24"
                          fill="none"
                          xmlns="http://www.w3.org/2000/svg"
                          className={`wphfc-transition-transform ${openDropdown === field ? "wphfc-rotate-180" : ""}`}>
                          <path
                            d="M6 9L12 15L18 9"
                            stroke="currentColor"
                            strokeWidth="1.5"
                            strokeLinecap="round"
                            strokeLinejoin="round"
                          />
                        </svg>
                      </button>
                      {openDropdown === field && (
                        <div className="wphfc-dropdown-menu wphfc-absolute wphfc-z-10 wphfc-w-full wphfc-mt-1 wphfc-bg-white wphfc-border wphfc-border-gray-300 wphfc-rounded-md wphfc-shadow-xl wphfc-max-h-60 wphfc-overflow-y-auto">
                          <ul className="wphfc-py-1">
                            <li>
                              <button
                                type="button"
                                className="wphfc-w-full wphfc-px-3 wphfc-py-2 wphfc-text-left wphfc-text-sm wphfc-text-gray-500 hover:wphfc-bg-gray-100 wphfc-cursor-pointer wphfc-border-none wphfc-bg-transparent"
                                onClick={() => handleFieldMapping(field, null)}>
                                -- None --
                              </button>
                            </li>
                            {formFields.length > 0 ? (
                              formFields.map((formField) => (
                                <li key={formField.id || formField.name}>
                                  <button
                                    type="button"
                                    className={`wphfc-w-full wphfc-px-3 wphfc-py-2 wphfc-text-left wphfc-text-sm hover:wphfc-bg-blue-50 wphfc-cursor-pointer wphfc-border-none wphfc-bg-transparent ${
                                      isFieldSelected(field, formField)
                                        ? "wphfc-bg-blue-100 wphfc-text-[#0B3966] wphfc-font-medium"
                                        : "wphfc-text-gray-700"
                                    }`}
                                    onClick={() =>
                                      handleFieldMapping(field, formField)
                                    }>
                                    {formField.label || formField.name}
                                  </button>
                                </li>
                              ))
                            ) : (
                              <li className="wphfc-px-3 wphfc-py-2 wphfc-text-sm wphfc-text-gray-500 wphfc-italic">
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
          )}

          {/* ─── Footer ─── */}
          <div className="wphfc-flex wphfc-justify-between wphfc-items-center wphfc-gap-3">
            {/* ─── Left: Pagination ─── */}
            {templateModal && !templateSettings && totalPages > 1 ? (
              <div className="wphfc-flex wphfc-items-center wphfc-gap-1">
                <button
                  onClick={() => setCurrentPage((p) => Math.max(p - 1, 1))}
                  disabled={currentPage === 1}
                  className="wphfc-px-2 wphfc-py-1 wphfc-text-xs wphfc-border wphfc-border-gray-300 wphfc-rounded wphfc-bg-white wphfc-cursor-pointer disabled:wphfc-opacity-40 disabled:wphfc-cursor-not-allowed hover:wphfc-bg-gray-50">
                  ‹ Prev
                </button>

                {Array.from({ length: totalPages }, (_, i) => i + 1)
                  .filter(
                    (page) =>
                      page === 1 ||
                      page === totalPages ||
                      Math.abs(page - currentPage) <= 1,
                  )
                  .reduce((acc, page, idx, arr) => {
                    if (idx > 0 && page - arr[idx - 1] > 1) acc.push("...");
                    acc.push(page);
                    return acc;
                  }, [])
                  .map((item, idx) =>
                    item === "..." ? (
                      <span
                        key={`dots-${idx}`}
                        className="wphfc-px-1 wphfc-text-xs wphfc-text-gray-400">
                        ...
                      </span>
                    ) : (
                      <button
                        key={item}
                        onClick={() => setCurrentPage(item)}
                        className={`wphfc-px-2 wphfc-py-1 wphfc-text-xs wphfc-border wphfc-rounded wphfc-cursor-pointer wphfc-transition-colors ${
                          currentPage === item
                            ? "wphfc-bg-primary wphfc-text-white wphfc-border-primary"
                            : "wphfc-border-gray-300 wphfc-bg-white hover:wphfc-bg-gray-50"
                        }`}>
                        {item}
                      </button>
                    ),
                  )}

                <button
                  onClick={() =>
                    setCurrentPage((p) => Math.min(p + 1, totalPages))
                  }
                  disabled={currentPage === totalPages}
                  className="wphfc-px-2 wphfc-py-1 wphfc-text-xs wphfc-border wphfc-border-gray-300 wphfc-rounded wphfc-bg-white wphfc-cursor-pointer disabled:wphfc-opacity-40 disabled:wphfc-cursor-not-allowed hover:wphfc-bg-gray-50">
                  Next ›
                </button>
              </div>
            ) : (
              <div />
            )}

            {/* ─── Right: Action Buttons ─── */}
            <div className="wphfc-flex wphfc-gap-3">
              <button
                className="wphfc-cancel-modal wphfc-px-4 wphfc-py-2 wphfc-text-sm wphfc-font-medium wphfc-bg-red-600 wphfc-text-white wphfc-border-none wphfc-rounded-md wphfc-cursor-pointer hover:wphfc-bg-red-700 disabled:wphfc-opacity-50 disabled:wphfc-cursor-not-allowed wphfc-transition-colors"
                onClick={() => {
                  if (templateSettings) {
                    setTemplateSettings(false);
                  } else if (templateModal) {
                    setTemplateModal(false);
                    setSearchQuery("");
                    setCurrentPage(1);
                  } else {
                    handleClose(false);
                  }
                }}
                disabled={isSaving}>
                {templateSettings || templateModal ? "BACK" : "CANCEL"}
              </button>

              {templateSettings ? (
                <button
                  className="wphfc-save-modal wphfc-px-4 wphfc-py-2 wphfc-text-sm wphfc-font-medium wphfc-bg-primary wphfc-text-white wphfc-border-none wphfc-rounded-md wphfc-cursor-pointer hover:wphfc-bg-primary/80 disabled:wphfc-opacity-50 disabled:wphfc-cursor-not-allowed wphfc-transition-colors"
                  onClick={handleTemplateSettingsSave}>
                  SAVE
                </button>
              ) : templateModal ? (
                <button
                  className="wphfc-save-modal wphfc-px-4 wphfc-py-2 wphfc-text-sm wphfc-font-medium wphfc-bg-primary wphfc-text-white wphfc-border-none wphfc-rounded-md wphfc-cursor-pointer hover:wphfc-bg-primary/80 disabled:wphfc-opacity-50 disabled:wphfc-cursor-not-allowed wphfc-transition-colors"
                  disabled={!selectedTemplate}
                  onClick={handleTemplateSelect}>
                  Choose this Template
                </button>
              ) : (
                <button
                  className="wphfc-next-modal wphfc-bg-primary wphfc-py-[10px] wphfc-px-[24px] wphfc-text-sm wphfc-text-white wphfc-border-none wphfc-rounded-md wphfc-cursor-pointer wphfc-flex wphfc-gap-2 hover:bg-primary/80 disabled:wphfc-opacity-50 disabled:wphfc-cursor-not-allowed"
                  onClick={handleNext}
                  disabled={isLoadingTemplates || isSaving}>
                  {isLoadingTemplates || isSaving ? (
                    <>
                      <svg
                        className="wphfc-animate-spin wphfc-h-4 wphfc-w-4 wphfc-text-white"
                        xmlns="http://www.w3.org/2000/svg"
                        fill="none"
                        viewBox="0 0 24 24">
                        <circle
                          className="wphfc-opacity-25"
                          cx="12"
                          cy="12"
                          r="10"
                          stroke="currentColor"
                          strokeWidth="4"
                        />
                        <path
                          className="wphfc-opacity-75"
                          fill="currentColor"
                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"
                        />
                      </svg>
                      {isSaving ? "SAVING..." : "LOADING..."}
                    </>
                  ) : (
                    <>
                      NEXT
                      <span className="arrow-icon">
                        <span>›</span>
                      </span>
                    </>
                  )}
                </button>
              )}
            </div>
          </div>
        </div>
      </div>
    </>
  );
};

export default WPHFC_HookModal;
