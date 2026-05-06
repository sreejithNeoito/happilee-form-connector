import React, { useEffect } from "react";
import { createPortal } from "react-dom";

const WPHFC_PreviewModal = ({ template, onClose }) => {
  const now = new Date().toLocaleTimeString([], {
    hour: "2-digit",
    minute: "2-digit",
  });

  // Close on Escape key
  useEffect(() => {
    const handleKeyDown = (e) => {
      if (e.key === "Escape") onClose();
    };
    document.addEventListener("keydown", handleKeyDown);
    return () => document.removeEventListener("keydown", handleKeyDown);
  }, [onClose]);

  return createPortal(
    <div
      className="wphfc-fixed wphfc-inset-0 wphfc-flex wphfc-items-center wphfc-justify-center wphfc-bg-black/60"
      style={{ zIndex: 999999 }}
      onClick={(e) => {
        if (e.target === e.currentTarget) onClose();
      }}>
      {/* Modal Box */}
      <div
        className="wphfc-bg-white wphfc-rounded-xl wphfc-shadow-2xl wphfc-overflow-hidden wphfc-flex wphfc-flex-col"
        style={{ width: "340px", maxHeight: "90vh" }}>
        {/* ─── Modal Header ─── */}
        <div className="wphfc-flex wphfc-items-center wphfc-justify-between wphfc-px-4 wphfc-py-3 wphfc-border-b wphfc-border-gray-200">
          <div>
            <h3 className="wphfc-text-sm wphfc-font-semibold wphfc-text-gray-800 wphfc-m-0">
              {template.template_name}
            </h3>
            <div className="wphfc-flex wphfc-gap-2 wphfc-mt-1">
              <span className="wphfc-text-xs wphfc-px-2 wphfc-py-0.5 wphfc-rounded-full wphfc-bg-blue-50 wphfc-text-blue-700 wphfc-font-medium">
                {template.category}
              </span>
              <span className="wphfc-text-xs wphfc-px-2 wphfc-py-0.5 wphfc-rounded-full wphfc-bg-gray-100 wphfc-text-gray-600">
                {template.language}
              </span>
            </div>
          </div>
          <button
            type="button"
            onClick={onClose}
            className="wphfc-p-1 wphfc-rounded-md wphfc-bg-transparent wphfc-border-none wphfc-cursor-pointer hover:wphfc-bg-gray-100 wphfc-transition-colors">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
              <path
                d="M17 7L7 17M7 7L17 17"
                stroke="#6b7280"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
              />
            </svg>
          </button>
        </div>

        {/* ─── WhatsApp UI ─── */}
        <div className="wphfc-flex wphfc-flex-col wphfc-flex-1 wphfc-overflow-hidden">
          {/* WhatsApp Top Bar */}
          <div
            className="wphfc-flex wphfc-items-center wphfc-gap-2 wphfc-px-3 wphfc-py-2"
            style={{ background: "#075e54" }}>
            {/* Avatar */}
            <div
              className="wphfc-flex wphfc-items-center wphfc-justify-center wphfc-rounded-full wphfc-flex-shrink-0"
              style={{ width: "34px", height: "34px", background: "#25d366" }}>
              <svg width="18" height="18" viewBox="0 0 24 24" fill="white">
                <path d="M12 12c2.7 0 4.8-2.1 4.8-4.8S14.7 2.4 12 2.4 7.2 4.5 7.2 7.2 9.3 12 12 12zm0 2.4c-3.2 0-9.6 1.6-9.6 4.8v2.4h19.2v-2.4c0-3.2-6.4-4.8-9.6-4.8z" />
              </svg>
            </div>
            <div className="wphfc-flex-1">
              <p
                className="wphfc-m-0"
                style={{
                  color: "white",
                  fontSize: "13px",
                  fontWeight: "600",
                  lineHeight: "1.2",
                }}>
                User Name
              </p>
              <p
                className="wphfc-m-0"
                style={{ color: "#b2dfdb", fontSize: "11px" }}>
                online
              </p>
            </div>
            {/* Icons */}
            <div className="wphfc-flex wphfc-gap-3 wphfc-items-center">
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
            className="wphfc-flex wphfc-flex-col wphfc-px-3 wphfc-py-4 wphfc-overflow-y-auto wphfc-flex-1"
            style={{
              background: "#efeae2",
              backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23d4cfc7' fill-opacity='0.4'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`,
              minHeight: "220px",
            }}>
            {/* TODAY Badge */}
            <div className="wphfc-flex wphfc-justify-center wphfc-mb-3">
              <span
                className="wphfc-text-xs wphfc-px-3 wphfc-py-1 wphfc-rounded-full"
                style={{
                  background: "rgba(255,255,255,0.75)",
                  color: "#555",
                  fontSize: "11px",
                }}>
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
                  padding: "8px 10px 22px 10px",
                  maxWidth: "88%",
                  minWidth: "120px",
                  boxShadow: "0 1px 2px rgba(0,0,0,0.15)",
                }}>
                {/* Bubble Tail */}
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

                {/* Header */}
                {template.header && (
                  <p
                    className="wphfc-m-0 wphfc-font-semibold wphfc-mb-1"
                    style={{ fontSize: "12px", color: "#111" }}>
                    {template.header}
                  </p>
                )}

                {/* Body */}
                <p
                  className="wphfc-m-0"
                  style={{
                    fontSize: "12px",
                    color: "#111",
                    whiteSpace: "pre-wrap",
                    lineHeight: "1.5",
                    wordBreak: "break-word",
                  }}>
                  {template.body}
                </p>

                {/* Footer */}
                {template.footer && (
                  <p
                    className="wphfc-m-0 wphfc-mt-1"
                    style={{ fontSize: "11px", color: "#888" }}>
                    {template.footer}
                  </p>
                )}

                {/* Time + Tick */}
                <div
                  className="wphfc-flex wphfc-items-center wphfc-gap-1"
                  style={{ position: "absolute", bottom: "5px", right: "8px" }}>
                  <span style={{ fontSize: "10px", color: "#888" }}>{now}</span>
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

            {/* Buttons preview */}
            {template.buttonArray && template.buttonArray.length > 0 && (
              <div className="wphfc-flex wphfc-flex-col wphfc-gap-1 wphfc-mt-1 wphfc-items-end">
                {template.buttonArray.map((btn, idx) => (
                  <div
                    key={idx}
                    className="wphfc-text-center wphfc-text-xs wphfc-font-medium wphfc-py-1 wphfc-px-3 wphfc-rounded-lg"
                    style={{
                      background: "white",
                      color: "#0a85d1",
                      boxShadow: "0 1px 2px rgba(0,0,0,0.15)",
                      minWidth: "120px",
                    }}>
                    {btn.text}
                  </div>
                ))}
              </div>
            )}
          </div>

          {/* WhatsApp Input Bar */}
          <div
            className="wphfc-flex wphfc-items-center wphfc-gap-2 wphfc-px-2 wphfc-py-2"
            style={{ background: "#f0f0f0", borderTop: "1px solid #ddd" }}>
            <div
              className="wphfc-flex-1 wphfc-rounded-full wphfc-px-3 wphfc-py-1"
              style={{ background: "white", fontSize: "12px", color: "#aaa" }}>
              Type a message
            </div>
            <div
              className="wphfc-flex wphfc-items-center wphfc-justify-center wphfc-rounded-full wphfc-flex-shrink-0"
              style={{ width: "32px", height: "32px", background: "#25d366" }}>
              <svg width="16" height="16" viewBox="0 0 24 24" fill="white">
                <path d="M2 21l21-9L2 3v7l15 2-15 2v7z" />
              </svg>
            </div>
          </div>
        </div>
      </div>
    </div>,
    document.body,
  );
};

export default WPHFC_PreviewModal;
