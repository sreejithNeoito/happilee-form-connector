import React, { useState, useEffect } from "react";
import { createPortal } from "react-dom";
import { ToastContainer, toast } from "react-toastify";
import "react-toastify/dist/ReactToastify.css";
import WPHFC_WhatsappPreview from "./WP-HFC-whatsappPreview";

const ChevronIcon = ({ isOpen }) => (
  <svg
    width="18"
    height="18"
    viewBox="0 0 24 24"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    className={`wphfc-shrink-0 wphfc-text-gray-500 wphfc-transition-transform wphfc-duration-200 ${isOpen ? "wphfc-rotate-180" : ""}`}>
    <path
      d="M6 9L12 15L18 9"
      stroke="currentColor"
      strokeWidth="2"
      strokeLinecap="round"
      strokeLinejoin="round"
    />
  </svg>
);

// Custom select: trigger button + floating option list with a click-away backdrop
const Dropdown = ({
  isOpen,
  setIsOpen,
  valueLabel,
  placeholder,
  disabled,
  header,
  children,
}) => (
  <div className="wphfc-relative wphfc-w-full">
    <button
      type="button"
      disabled={disabled}
      onClick={() => setIsOpen(!isOpen)}
      className={`wphfc-w-full wphfc-px-3 wphfc-py-2 wphfc-bg-white wphfc-border wphfc-border-solid wphfc-rounded-lg wphfc-flex wphfc-items-center wphfc-justify-between wphfc-gap-2 wphfc-text-sm wphfc-text-left wphfc-cursor-pointer wphfc-transition-colors disabled:wphfc-cursor-not-allowed disabled:wphfc-bg-gray-50 ${
        isOpen
          ? "wphfc-border-blue-500 wphfc-ring-2 wphfc-ring-blue-100"
          : "wphfc-border-gray-300 hover:wphfc-border-gray-400"
      }`}>
      <span
        className={`wphfc-truncate ${valueLabel ? "wphfc-text-gray-900" : "wphfc-text-gray-400"}`}>
        {valueLabel || placeholder}
      </span>
      <ChevronIcon isOpen={isOpen} />
    </button>
    {isOpen && (
      <>
        <div
          className="wphfc-fixed wphfc-inset-0 wphfc-z-10"
          onClick={() => setIsOpen(false)}
        />
        <div className="wphfc-absolute wphfc-z-20 wphfc-w-full wphfc-mt-1 wphfc-bg-white wphfc-border wphfc-border-solid wphfc-border-gray-200 wphfc-rounded-lg wphfc-shadow-lg wphfc-overflow-hidden">
          {header}
          <ul className="wphfc-m-0 wphfc-py-1 wphfc-max-h-56 wphfc-overflow-y-auto">
            {children}
          </ul>
        </div>
      </>
    )}
  </div>
);

const DropdownOption = ({ selected, muted, onClick, children }) => (
  <li className="wphfc-m-0">
    <button
      type="button"
      onClick={onClick}
      className={`wphfc-w-full wphfc-px-3 wphfc-py-2 wphfc-text-left wphfc-text-sm wphfc-cursor-pointer wphfc-border-none wphfc-transition-colors ${
        selected
          ? "wphfc-bg-blue-50 wphfc-text-primary wphfc-font-medium"
          : muted
            ? "wphfc-bg-transparent wphfc-text-gray-400 hover:wphfc-bg-gray-50"
            : "wphfc-bg-transparent wphfc-text-gray-700 hover:wphfc-bg-gray-50"
      }`}>
      {children}
    </button>
  </li>
);

const EmptyOption = ({ children }) => (
  <li className="wphfc-m-0 wphfc-px-3 wphfc-py-2 wphfc-text-sm wphfc-text-gray-400 wphfc-italic">
    {children}
  </li>
);

const Section = ({ step, title, description, children }) => (
  <div className="wphfc-bg-white wphfc-border wphfc-border-solid wphfc-border-gray-200 wphfc-rounded-xl wphfc-p-4">
    <div className="wphfc-flex wphfc-items-start wphfc-gap-3 wphfc-mb-4">
      <span className="wphfc-flex wphfc-items-center wphfc-justify-center wphfc-shrink-0 wphfc-w-6 wphfc-h-6 wphfc-rounded-full wphfc-bg-primary wphfc-text-white wphfc-text-xs wphfc-font-semibold">
        {step}
      </span>
      <div>
        <h4 className="wphfc-m-0 wphfc-text-sm wphfc-font-semibold wphfc-text-gray-800">
          {title}
        </h4>
        {description && (
          <p className="wphfc-m-0 wphfc-mt-0.5 wphfc-text-xs wphfc-text-gray-500">
            {description}
          </p>
        )}
      </div>
    </div>
    {children}
  </div>
);

const FieldRow = ({ label, required, labelWidth = "sm:wphfc-w-32", children }) => (
  <div className="wphfc-flex wphfc-flex-col sm:wphfc-flex-row sm:wphfc-items-center wphfc-gap-1 sm:wphfc-gap-3">
    <label
      className={`wphfc-text-sm wphfc-font-medium wphfc-text-gray-700 ${labelWidth} wphfc-shrink-0`}>
      {label}
      {required && <span className="wphfc-ml-1 wphfc-text-red-500">*</span>}
    </label>
    <div className="wphfc-flex-1 wphfc-min-w-0">{children}</div>
  </div>
);

const WPHFC_HookModal = ({
  selectedFormId,
  formType,
  onClose,
  activeHook,
  formName,
  savedFields,
}) => {
  const [selectedHook, setSelectedHook] = useState("");
  const [isSaving, setIsSaving] = useState(false);
  const [formFields, setFormFields] = useState([]);
  const [fieldMappings, setFieldMappings] = useState({});
  const [openDropdown, setOpenDropdown] = useState(null);
  const [isClosing, setIsClosing] = useState(false);
  const [templates, setTemplates] = useState([]);
  const [isLoadingTemplates, setIsLoadingTemplates] = useState(false);
  const [selectedTemplate, setSelectedTemplate] = useState(null);
  const [templateSearch, setTemplateSearch] = useState("");
  const [paramMappings, setParamMappings] = useState({});

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

  const approvedTemplates = templates.filter((t) => t.status === "approved");
  const searchedTemplates = approvedTemplates.filter((t) => {
    const query = templateSearch.toLowerCase();
    return (
      t.template_name?.toLowerCase().includes(query) ||
      t.category?.toLowerCase().includes(query) ||
      t.language?.toLowerCase().includes(query)
    );
  });
  const templateParams = selectedTemplate?.total_params || [];

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

  // Only one dropdown can be open at a time
  const dropdownState = (name) => ({
    isOpen: openDropdown === name,
    setIsOpen: (open) => setOpenDropdown(open ? name : null),
  });

  useEffect(() => {
    getFormFields(selectedFormId, formType);
    loadTemplates();
  }, [selectedFormId, formType]);

  // Load saved mappings keyed by display label; fields not shown here are saved back unchanged
  useEffect(() => {
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
  }, [savedFields]);

  useEffect(() => {
    const availableHooks = getAvailableHooks(formType);
    if (activeHook && availableHooks.some((h) => h.value === activeHook)) {
      setSelectedHook(activeHook);
    } else if (availableHooks.length > 0) {
      setSelectedHook(availableHooks[0].value);
    }
  }, [activeHook, formType]);

  // Lock page scroll behind the modal while it is open
  useEffect(() => {
    document.body.classList.add("wphfc-modal-open");
    return () => document.body.classList.remove("wphfc-modal-open");
  }, []);

  // Escape closes an open dropdown first, then the modal
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key !== "Escape" || isSaving) return;
      if (openDropdown) setOpenDropdown(null);
      else handleClose(false);
    };
    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [openDropdown, isSaving]);

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
      console.error("Error fetching form fields:", error);
    }
  };

  const fetchTemplates = async () => {
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
      if (response.ok) {
        const data = await response.json();
        if (data.success && Array.isArray(data.template_messages?.data)) {
          return data.template_messages.data;
        }
      }
    } catch (error) {
      console.error("Error fetching template messages:", error);
    }
    return [];
  };

  const getSavedTemplateSettings = async () => {
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
        return data.template_settings;
      }
      return null;
    } catch (error) {
      console.error("Failed to fetch template settings:", error);
      return null;
    }
  };

  // Load the template list and pre-select this form's saved template + param mappings.
  // The list only holds "wp_" templates, so a saved template outside it is not pre-selected.
  const loadTemplates = async () => {
    setIsLoadingTemplates(true);
    const [templateList, savedSettings] = await Promise.all([
      fetchTemplates(),
      getSavedTemplateSettings(),
    ]);
    setTemplates(templateList);

    const savedTemplate = savedSettings?.template_id
      ? templateList.find(
          (t) => String(t.template_id) === String(savedSettings.template_id),
        )
      : null;

    if (savedTemplate) {
      setSelectedTemplate(savedTemplate);
      try {
        const parsedMappings =
          typeof savedSettings.param_mappings === "string"
            ? JSON.parse(savedSettings.param_mappings)
            : savedSettings.param_mappings;
        if (parsedMappings && typeof parsedMappings === "object") {
          setParamMappings(parsedMappings);
        }
      } catch (e) {
        console.error("Failed to parse saved param_mappings:", e);
      }
    }
    setIsLoadingTemplates(false);
  };

  // Value stored for a form field; Forminator keys submissions by field id
  const getFormFieldValue = (formField) =>
    formType === "forminator"
      ? formField.id || formField.name
      : formField.name || formField.id;

  const getFormFieldLabel = (value) => {
    if (!value) return "";
    const formField = formFields.find((f) => getFormFieldValue(f) === value);
    return formField ? formField.label || formField.name : value;
  };

  const handleClose = (shouldRefresh) => {
    setIsClosing(true);
    setTimeout(() => {
      onClose(shouldRefresh);
    }, 300);
  };

  const handleFieldMapping = (happileeField, formField) => {
    setFieldMappings((prev) => ({
      ...prev,
      [happileeField]: formField ? getFormFieldValue(formField) : null,
    }));
    setOpenDropdown(null);
  };

  // A null template is the default "Select a Template" option, which clears the selection
  const handleTemplateSelect = (template) => {
    if (
      String(selectedTemplate?.template_id) !== String(template?.template_id)
    ) {
      setParamMappings({});
    }
    setSelectedTemplate(template);
    setTemplateSearch("");
    setOpenDropdown(null);
  };

  const handleParamMapping = (param, formField) => {
    setParamMappings((prev) => ({
      ...prev,
      [param]: formField ? getFormFieldValue(formField) : "",
    }));
    setOpenDropdown(null);
  };

  // Save form settings, then the template settings
  const handleSave = async () => {
    if (!selectedHook) {
      notify("Please select a hook.", "error");
      return;
    }
    if (!selectedTemplate) {
      notify("Please select a template.", "error");
      return;
    }
    if (!fieldMappings["Mobile"]) {
      notify(
        "Please select the form field that holds the mobile number.",
        "error",
      );
      return;
    }
    const emptyParams = templateParams.filter((param) => !paramMappings[param]);
    if (emptyParams.length > 0) {
      notify(
        `Please map all template parameters. Missing: ${emptyParams.map((p) => `{{${p}}}`).join(", ")}`,
        "error",
      );
      return;
    }

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

    // Returns null on success, otherwise the error message from the API
    const post = async (endpoint, body) => {
      const response = await fetch(`${happileeConnect.rest_url}${endpoint}`, {
        method: "POST",
        headers: {
          "Content-Type": "application/json",
          "X-WP-Nonce": happileeConnect.happfoco_nonce,
        },
        body: JSON.stringify(body),
        credentials: "same-origin",
      });
      const data = await response.json();
      return response.ok && data.success ? null : data.message || "";
    };

    setIsSaving(true);
    try {
      const formError = await post("save-form-settings", {
        form_id: String(selectedFormId),
        form_name: formName || "",
        form_type: formType,
        active_hook: selectedHook,
        is_enabled: 1,
        form_field: keyedFieldMappings,
      });
      if (formError !== null) {
        notify(formError || "Failed to save form settings.", "error");
        return;
      }

      const templateError = await post("save-template-settings", {
        form_id: String(selectedFormId),
        form_type: formType,
        template_id: selectedTemplate.template_id,
        template_name: selectedTemplate.template_name,
        template_type: selectedTemplate.template_type || "",
        param_mappings: paramMappings,
      });
      if (templateError !== null) {
        notify(templateError || "Failed to save template settings.", "error");
        return;
      }

      notify("Settings saved successfully!", "success");
      setTimeout(() => handleClose(true), 600);
    } catch (error) {
      notify("An error occurred while saving.", "error");
    } finally {
      setIsSaving(false);
    }
  };

  const availableHooks = getAvailableHooks(formType);
  const selectedHookLabel =
    availableHooks.find((h) => h.value === selectedHook)?.label || "";

  const formFieldOptions = (isSelected, onPick) =>
    formFields.length > 0 ? (
      formFields.map((formField) => (
        <DropdownOption
          key={formField.id || formField.name}
          selected={isSelected(getFormFieldValue(formField))}
          onClick={() => onPick(formField)}>
          {formField.label || formField.name}
        </DropdownOption>
      ))
    ) : (
      <EmptyOption>No fields available</EmptyOption>
    );

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
        className={`wphfc-modal-backdrop wphfc-fixed wphfc-inset-0 wphfc-flex wphfc-items-center wphfc-justify-center wphfc-bg-black/50 wphfc-transition-opacity wphfc-duration-300 ${isClosing ? "wphfc-opacity-0" : "wphfc-opacity-100"}`}
        style={{ zIndex: 100000 }}
        onClick={(e) => {
          if (e.target === e.currentTarget && !isSaving) handleClose(false);
        }}>
        <div
          className={`wphfc-max-w-5xl wphfc-w-full wphfc-mx-4 wphfc-bg-white wphfc-rounded-xl wphfc-shadow-2xl wphfc-flex wphfc-flex-col wphfc-overflow-hidden wphfc-transition-transform wphfc-duration-300 ${
            isClosing
              ? "wphfc-scale-95 wphfc-opacity-0"
              : "wphfc-scale-100 wphfc-opacity-100"
          }`}
          style={{ maxHeight: "90vh" }}>
          {/* Header */}
          <div className="wphfc-flex wphfc-justify-between wphfc-items-center wphfc-gap-4 wphfc-px-6 wphfc-py-4 wphfc-border-0 wphfc-border-b wphfc-border-solid wphfc-border-gray-200">
            <div className="wphfc-min-w-0">
              <h3 className="wphfc-text-lg wphfc-text-primary wphfc-font-semibold wphfc-m-0 wphfc-truncate">
                Configure {formName || "Form"}
              </h3>
              <p className="wphfc-m-0 wphfc-mt-0.5 wphfc-text-xs wphfc-text-gray-500">
                Form ID: {selectedFormId}
              </p>
            </div>
            <button
              type="button"
              className="wphfc-modal-close wphfc-p-1.5 wphfc-rounded-md hover:wphfc-bg-gray-100 wphfc-transition-colors"
              onClick={() => handleClose(false)}
              disabled={isSaving}
              aria-label="Close">
              <svg
                className="wphfc-w-5 wphfc-h-5 wphfc-text-gray-500"
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

          {/* Body */}
          <div className="wphfc-flex-1 wphfc-overflow-y-auto wphfc-bg-gray-50 wphfc-px-6 wphfc-py-5">
            <div className="wphfc-flex wphfc-flex-col lg:wphfc-flex-row wphfc-gap-5">
              {/* Left: Settings */}
              <div className="wphfc-flex-1 wphfc-min-w-0 wphfc-flex wphfc-flex-col wphfc-gap-4">
                <Section
                  step={1}
                  title="Trigger Hook"
                  description="Choose when the action runs in the form submission process.">
                  <Dropdown
                    {...dropdownState("hook")}
                    valueLabel={selectedHookLabel}
                    placeholder="Please Select the Hook"
                    disabled={isSaving}>
                    {availableHooks.map((hook) => (
                      <DropdownOption
                        key={hook.value}
                        selected={selectedHook === hook.value}
                        onClick={() => {
                          setSelectedHook(hook.value);
                          setOpenDropdown(null);
                        }}>
                        {hook.label}
                      </DropdownOption>
                    ))}
                  </Dropdown>
                </Section>

                <Section
                  step={2}
                  title="Happilee Field Mapping"
                  description="Pick the approved WhatsApp template to send and fill it from your form fields.">
                  <div className="wphfc-flex wphfc-flex-col wphfc-gap-3">
                    <FieldRow label="Template" required>
                      <Dropdown
                        {...dropdownState("template")}
                        valueLabel={selectedTemplate?.template_name}
                        placeholder={
                          isLoadingTemplates
                            ? "Loading templates..."
                            : "Select a Template"
                        }
                        disabled={isSaving || isLoadingTemplates}
                        header={
                          approvedTemplates.length > 5 && (
                            <div className="wphfc-p-2 wphfc-border-0 wphfc-border-b wphfc-border-solid wphfc-border-gray-100">
                              <input
                                type="text"
                                autoFocus
                                placeholder="Search templates..."
                                value={templateSearch}
                                onChange={(e) =>
                                  setTemplateSearch(e.target.value)
                                }
                                className="wphfc-w-full wphfc-px-3 wphfc-py-1.5 wphfc-text-sm wphfc-border wphfc-border-solid wphfc-border-gray-300 wphfc-rounded-md"
                                style={{ outline: "none", boxShadow: "none" }}
                              />
                            </div>
                          )
                        }>
                        <DropdownOption
                          muted
                          selected={!selectedTemplate}
                          onClick={() => handleTemplateSelect(null)}>
                          -- Select a Template --
                        </DropdownOption>
                        {searchedTemplates.length > 0 ? (
                          searchedTemplates.map((template) => (
                            <DropdownOption
                              key={template.template_id}
                              selected={
                                String(selectedTemplate?.template_id) ===
                                String(template.template_id)
                              }
                              onClick={() => handleTemplateSelect(template)}>
                              <span className="wphfc-flex wphfc-items-center wphfc-justify-between wphfc-gap-2">
                                <span className="wphfc-truncate">
                                  {template.template_name}
                                </span>
                                {template.category && (
                                  <span className="wphfc-shrink-0 wphfc-text-[10px] wphfc-uppercase wphfc-tracking-wide wphfc-px-1.5 wphfc-py-0.5 wphfc-rounded wphfc-bg-gray-100 wphfc-text-gray-500">
                                    {template.category}
                                  </span>
                                )}
                              </span>
                            </DropdownOption>
                          ))
                        ) : (
                          <EmptyOption>
                            {approvedTemplates.length > 0
                              ? "No templates match your search."
                              : 'No templates available. Create an approved template whose name starts with "wp_" in your Happilee account.'}
                          </EmptyOption>
                        )}
                      </Dropdown>
                    </FieldRow>

                    {["First Name", "Last Name"].map((label) => (
                      <FieldRow key={label} label={label}>
                        <Dropdown
                          {...dropdownState(label)}
                          valueLabel={getFormFieldLabel(fieldMappings[label])}
                          placeholder={`Select the ${label.toLowerCase()} field`}
                          disabled={isSaving}>
                          <DropdownOption
                            muted
                            selected={!fieldMappings[label]}
                            onClick={() => handleFieldMapping(label, null)}>
                            -- None --
                          </DropdownOption>
                          {formFieldOptions(
                            (value) => fieldMappings[label] === value,
                            (formField) => handleFieldMapping(label, formField),
                          )}
                        </Dropdown>
                      </FieldRow>
                    ))}

                    <FieldRow label="Mobile" required>
                      <Dropdown
                        {...dropdownState("mobile")}
                        valueLabel={getFormFieldLabel(fieldMappings["Mobile"])}
                        placeholder="Select the phone number field"
                        disabled={isSaving}>
                        {formFieldOptions(
                          (value) => fieldMappings["Mobile"] === value,
                          (formField) =>
                            handleFieldMapping("Mobile", formField),
                        )}
                      </Dropdown>
                    </FieldRow>

                    <FieldRow label="Tags">
                      <input
                        type="text"
                        className="wphfc-text-input"
                        placeholder="Enter tags (comma separated)"
                        value={fieldMappings["Tags"] || ""}
                        onChange={(e) =>
                          setFieldMappings((prev) => ({
                            ...prev,
                            Tags: e.target.value,
                          }))
                        }
                        disabled={isSaving}
                      />
                    </FieldRow>
                  </div>

                  {selectedTemplate && (
                    <div className="wphfc-mt-4 wphfc-pt-4 wphfc-border-0 wphfc-border-t wphfc-border-dashed wphfc-border-gray-200">
                      <p className="wphfc-m-0 wphfc-mb-3 wphfc-text-xs wphfc-font-semibold wphfc-uppercase wphfc-tracking-wide wphfc-text-gray-500">
                        Parameter Mapping
                      </p>
                      {templateParams.length > 0 ? (
                        <div className="wphfc-flex wphfc-flex-col wphfc-gap-3">
                          {templateParams.map((param) => (
                            <FieldRow
                              key={param}
                              labelWidth="sm:wphfc-w-48"
                              label={
                                <span
                                  className="wphfc-inline-block wphfc-max-w-full wphfc-break-all wphfc-text-xs wphfc-px-2 wphfc-py-1 wphfc-rounded wphfc-font-mono"
                                  style={{
                                    background: "#fef9c3",
                                    color: "#854d0e",
                                    border: "1px solid #fde047",
                                  }}>
                                  {`{{${param}}}`}
                                </span>
                              }>
                              <Dropdown
                                {...dropdownState(`param:${param}`)}
                                valueLabel={getFormFieldLabel(
                                  paramMappings[param],
                                )}
                                placeholder="Select a form field"
                                disabled={isSaving}>
                                <DropdownOption
                                  muted
                                  onClick={() =>
                                    handleParamMapping(param, null)
                                  }>
                                  -- None --
                                </DropdownOption>
                                {formFieldOptions(
                                  (value) => paramMappings[param] === value,
                                  (formField) =>
                                    handleParamMapping(param, formField),
                                )}
                              </Dropdown>
                            </FieldRow>
                          ))}
                        </div>
                      ) : (
                        <p className="wphfc-m-0 wphfc-text-sm wphfc-text-gray-500 wphfc-italic">
                          This template has no dynamic parameters.
                        </p>
                      )}
                    </div>
                  )}
                </Section>
              </div>

              {/* Right: Live Preview */}
              <div className="lg:wphfc-w-[320px] wphfc-shrink-0">
                <div className="lg:wphfc-sticky lg:wphfc-top-0">
                  <div className="wphfc-flex wphfc-items-center wphfc-justify-between wphfc-mb-2">
                    <p className="wphfc-m-0 wphfc-text-sm wphfc-font-semibold wphfc-text-gray-800">
                      Live Preview
                    </p>
                    {selectedTemplate?.language && (
                      <span className="wphfc-text-xs wphfc-px-2 wphfc-py-0.5 wphfc-rounded-full wphfc-bg-gray-200 wphfc-text-gray-600">
                        {selectedTemplate.language}
                      </span>
                    )}
                  </div>
                  <WPHFC_WhatsappPreview
                    template={selectedTemplate}
                    getParamLabel={(param) =>
                      getFormFieldLabel(paramMappings[param])
                    }
                  />
                </div>
              </div>
            </div>
          </div>

          {/* Footer */}
          <div className="wphfc-flex wphfc-justify-end wphfc-items-center wphfc-gap-3 wphfc-px-6 wphfc-py-4 wphfc-border-0 wphfc-border-t wphfc-border-solid wphfc-border-gray-200 wphfc-bg-white">
            <button
              type="button"
              className="wphfc-cancel-modal wphfc-px-4 wphfc-py-2 wphfc-text-sm wphfc-font-medium wphfc-bg-white wphfc-text-gray-700 wphfc-border wphfc-border-solid wphfc-border-gray-300 wphfc-rounded-lg hover:wphfc-bg-gray-50 disabled:wphfc-opacity-50 disabled:wphfc-cursor-not-allowed wphfc-transition-colors"
              onClick={() => handleClose(false)}
              disabled={isSaving}>
              CANCEL
            </button>
            <button
              type="button"
              className="wphfc-save-modal wphfc-flex wphfc-items-center wphfc-gap-2 wphfc-px-5 wphfc-py-2 wphfc-text-sm wphfc-font-medium wphfc-bg-primary wphfc-text-white wphfc-border-none wphfc-rounded-lg hover:wphfc-bg-primary/80 disabled:wphfc-opacity-50 disabled:wphfc-cursor-not-allowed wphfc-transition-colors"
              onClick={handleSave}
              disabled={isSaving || isLoadingTemplates}>
              {isSaving && (
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
              )}
              {isSaving ? "SAVING..." : "SAVE"}
            </button>
          </div>
        </div>
      </div>
    </>
  );
};

export default WPHFC_HookModal;
