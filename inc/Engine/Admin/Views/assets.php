<?php
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );
// Views/assets.php — CSS styles for the admin panel
?>
        <style>
            :root {
                --uwb-primary: <?php echo esc_attr( $primary_color ); ?>;
                --uwb-primary-dark: <?php echo esc_attr( $primary_dark ); ?>;
                --uwb-success: #10b981;
                --uwb-warning: #f59e0b;
                --uwb-danger: #ef4444;
                --uwb-bg: #f8fafc;
                --uwb-card-bg: #ffffff;
                --uwb-text: #1e293b;
                --uwb-text-muted: #64748b;
                --uwb-border: #e2e8f0;
            }

            .uwb-dashboard-wrap {
                margin: 20px 20px 0 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
                color: var(--uwb-text);
            }

            .uwb-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                background: <?php echo esc_attr( $header_bg_start ); ?>;
                padding: 24px 32px;
                border-radius: 16px;
                box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.08), 0 4px 6px -2px rgba(0, 0, 0, 0.04);
                color: #ffffff;
                margin-bottom: 24px;
                border-left: 4px solid var(--uwb-primary);
            }

            .uwb-header-title h1 {
                margin: 0;
                color: #ffffff;
                font-size: 24px;
                font-weight: 800;
                letter-spacing: -0.5px;
                text-shadow: 0 2px 4px rgba(0,0,0,0.1);
            }

            .uwb-header-title p {
                margin: 6px 0 0 0;
                opacity: 0.9;
                font-size: 14px;
            }

            .uwb-header-actions {
                display: flex;
                gap: 12px;
            }

            .uwb-btn-purge {
                background: rgba(255, 255, 255, 0.2);
                border: 1px solid rgba(255, 255, 255, 0.3);
                color: #ffffff;
                padding: 10px 20px;
                font-weight: 600;
                border-radius: 8px;
                text-decoration: none;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 14px;
            }

            .uwb-btn-purge:hover {
                background: #ffffff;
                color: var(--uwb-primary-dark);
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }

            .uwb-layout {
                display: grid;
                grid-template-columns: 240px 1fr;
                gap: 24px;
                transition: grid-template-columns 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .uwb-layout.collapsed {
                grid-template-columns: 78px 1fr;
            }

            .uwb-sidebar-nav {
                background: var(--uwb-card-bg);
                border-radius: 12px;
                border: 1px solid var(--uwb-border);
                padding: 16px;
                display: flex;
                flex-direction: column;
                gap: 8px;
                height: fit-content;
                transition: all 0.3s ease;
                overflow: hidden;
            }

            .uwb-layout.collapsed .uwb-sidebar-nav {
                padding: 16px 8px;
            }

            .uwb-sidebar-toggle {
                display: flex;
                align-items: center;
                justify-content: flex-end;
                padding: 8px;
                cursor: pointer;
                color: var(--uwb-text-muted);
                border-bottom: 1px solid var(--uwb-border);
                margin-bottom: 8px;
                transition: all 0.2s ease;
            }

            .uwb-layout.collapsed .uwb-sidebar-toggle {
                justify-content: center;
                border-bottom: none;
                margin-bottom: 0;
            }

            .uwb-nav-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px 16px;
                border-radius: 8px;
                color: var(--uwb-text-muted);
                text-decoration: none;
                font-weight: 600;
                font-size: 14px;
                transition: all 0.2s ease;
                cursor: pointer;
            }

            .uwb-nav-item span {
                transition: opacity 0.2s ease;
                white-space: nowrap;
            }

            .uwb-layout.collapsed .uwb-nav-item span {
                display: none;
                opacity: 0;
            }

            .uwb-layout.collapsed .uwb-nav-item {
                justify-content: center;
                padding: 12px;
            }

            .uwb-nav-item:hover, .uwb-nav-item.active {
                background: #f1f5f9;
                color: var(--uwb-primary);
            }

            .uwb-nav-item.active {
                background: #e0e7ff;
                color: var(--uwb-primary-dark);
            }

            /* Sub-tabs Styling */
            .uwb-sub-tabs-nav {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                border-bottom: 2px solid var(--uwb-border);
                margin-bottom: 24px;
                padding-bottom: 12px;
            }

            .uwb-sub-tab-item {
                padding: 10px 18px;
                font-weight: 700;
                font-size: 13.5px;
                color: var(--uwb-text-muted);
                cursor: pointer;
                border-radius: 8px;
                transition: all 0.2s ease;
                background: #f8fafc;
                border: 1px solid var(--uwb-border);
            }

            .uwb-sub-tab-item:hover, .uwb-sub-tab-item.active {
                background: #e0e7ff;
                color: var(--uwb-primary-dark);
                border-color: #c7d2fe;
            }

            .uwb-subtab-content {
                display: none;
            }

            .uwb-subtab-content.active {
                display: block;
            }

            .uwb-content-panel {
                background: var(--uwb-card-bg);
                border-radius: 12px;
                border: 1px solid var(--uwb-border);
                padding: 32px;
                box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            }

            .uwb-tab-content {
                display: none;
            }

            .uwb-tab-content.active {
                display: block;
            }

            .uwb-form-group {
                margin-bottom: 24px;
                max-width: 700px;
            }

            .uwb-form-group label {
                display: block;
                font-weight: 600;
                margin-bottom: 8px;
                color: var(--uwb-text);
                font-size: 14px;
            }

            .uwb-form-group input[type="text"],
            .uwb-form-group input[type="number"],
            .uwb-form-group textarea {
                width: 100%;
                border: 1px solid var(--uwb-border);
                border-radius: 8px;
                padding: 12px;
                font-size: 14px;
                color: var(--uwb-text);
                background-color: var(--uwb-bg);
                transition: border-color 0.2s ease, box-shadow 0.2s ease;
            }

            .uwb-form-group input:focus,
            .uwb-form-group textarea:focus {
                border-color: var(--uwb-primary);
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.15);
                outline: none;
            }

            .uwb-form-group .description {
                margin-top: 6px;
                color: var(--uwb-text-muted);
                font-size: 12.5px;
                line-height: 1.4;
            }

            /* Preloader Progress CSS */
            .uwb-preload-status-box {
                background: var(--uwb-bg);
                border-radius: 10px;
                padding: 24px;
                border: 1px solid var(--uwb-border);
                margin-bottom: 24px;
            }

            .uwb-stats-grid {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 16px;
                margin-bottom: 24px;
            }

            .uwb-stat-card {
                background: var(--uwb-card-bg);
                border: 1px solid var(--uwb-border);
                border-radius: 8px;
                padding: 16px;
                text-align: center;
                box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            }

            .uwb-stat-card .num {
                font-size: 28px;
                font-weight: 800;
                margin-bottom: 4px;
            }

            .uwb-stat-card .label {
                font-size: 12px;
                font-weight: 600;
                color: var(--uwb-text-muted);
                text-transform: uppercase;
            }

            .uwb-stat-pending .num { color: var(--uwb-text); }
            .uwb-stat-processing .num { color: var(--uwb-warning); }
            .uwb-stat-completed .num { color: var(--uwb-success); }
            .uwb-stat-failed .num { color: var(--uwb-danger); }

            .uwb-progress-bar-wrap {
                background: #e2e8f0;
                border-radius: 100px;
                height: 12px;
                width: 100%;
                overflow: hidden;
                margin-bottom: 12px;
            }

            .uwb-progress-bar-fill {
                background: linear-gradient(90deg, #10b981 0%, #059669 100%);
                height: 100%;
                width: 0%;
                transition: width 0.3s ease;
            }

            .uwb-progress-text {
                display: flex;
                justify-content: space-between;
                font-size: 13px;
                font-weight: 600;
                color: var(--uwb-text);
            }

            .uwb-preload-actions {
                display: flex;
                gap: 12px;
            }

            .uwb-btn-action {
                border-radius: 8px;
                padding: 10px 18px;
                font-size: 13.5px;
                font-weight: 600;
                cursor: pointer;
                border: 1px solid transparent;
                transition: all 0.2s ease;
                display: inline-flex;
                align-items: center;
                gap: 6px;
            }

            .uwb-btn-start { background: var(--uwb-primary); color: #ffffff; }
            .uwb-btn-start:hover { background: var(--uwb-primary-dark); }
            .uwb-btn-stop { background: var(--uwb-warning); color: #ffffff; }
            .uwb-btn-stop:hover { background: #d97706; }
            .uwb-btn-clear { background: #f1f5f9; border-color: #cbd5e1; color: var(--uwb-text); }
            .uwb-btn-clear:hover { background: #e2e8f0; }

            .uwb-nginx-instructions {
                background: #0f172a;
                color: #f8fafc;
                padding: 24px;
                border-radius: 10px;
                font-family: monospace;
                overflow-x: auto;
                font-size: 13px;
                line-height: 1.6;
                border-left: 4px solid var(--uwb-primary);
            }

            #uwb-filter-wc {
                transition: all 0.2s ease;
            }
            #uwb-filter-wc:hover {
                background: #f1f5f9 !important;
                border-color: #cbd5e1 !important;
            }
            #uwb-filter-wc.active {
                background: #e0e7ff !important;
                color: var(--uwb-primary-dark) !important;
                border-color: var(--uwb-primary) !important;
            }
            .uwb-btn-purge:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }
            .uwb-spinner {
                width: 16px;
                height: 16px;
                border: 2px solid #ffffff;
                border-bottom-color: transparent;
                border-radius: 50%;
                display: inline-block;
                box-sizing: border-box;
                animation: uwb-rotation 1s linear infinite;
            }
            @keyframes uwb-rotation {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            @keyframes uwb-rotation {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }

            /* Vertical Cache Tree Layout */
            .uwb-pipeline-container {
                background: #f8fafc;
                border: 1px solid var(--uwb-border);
                border-radius: 12px;
                padding: 24px;
                margin-bottom: 24px;
            }
            .uwb-pipeline-tree {
                display: flex;
                flex-direction: column;
                gap: 0;
                position: relative;
                margin-top: 16px;
            }
            .uwb-pipeline-tree::before {
                content: '';
                position: absolute;
                left: 27px;
                top: 24px;
                bottom: 24px;
                width: 2px;
                background: var(--uwb-border);
                z-index: 1;
            }
            .uwb-tree-node {
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: var(--uwb-card-bg);
                border: 1px solid var(--uwb-border);
                border-radius: 10px;
                padding: 16px 20px;
                margin-bottom: 12px;
                position: relative;
                z-index: 2;
                transition: all 0.2s ease;
                box-shadow: 0 1px 3px rgba(0,0,0,0.02);
                box-sizing: border-box;
            }
            .uwb-tree-node:hover {
                transform: translateX(4px);
                box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
            }
            .node-status-left {
                width: 16px;
                height: 16px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 16px;
                flex-shrink: 0;
            }
            .uwb-tree-node.active .node-status-left {
                background: #d1fae5;
                border: 2px solid #10b981;
            }
            .uwb-tree-node.inactive .node-status-left {
                background: #f1f5f9;
                border: 2px solid #94a3b8;
            }
            .uwb-tree-node.active .node-status-left::after {
                content: '';
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: #10b981;
            }
            .uwb-tree-node.inactive .node-status-left::after {
                content: '';
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background: #94a3b8;
            }
            .node-info-mid {
                display: flex;
                align-items: center;
                flex: 1;
                margin-right: 20px;
            }
            .node-icon-wrap {
                width: 36px;
                height: 36px;
                border-radius: 8px;
                background: #f1f5f9;
                color: var(--uwb-text-muted);
                display: flex;
                align-items: center;
                justify-content: center;
                margin-right: 16px;
                flex-shrink: 0;
            }
            .uwb-tree-node.active .node-icon-wrap {
                background: #e0e7ff;
                color: var(--uwb-primary);
            }
            .node-text-wrap {
                display: flex;
                flex-direction: column;
                gap: 2px;
            }
            .node-title {
                font-weight: 700;
                font-size: 13.5px;
                color: var(--uwb-text);
            }
            .node-desc {
                font-size: 12px;
                color: var(--uwb-text-muted);
            }
            .node-action-right {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-shrink: 0;
            }
            .uwb-btn-mini {
                padding: 6px 12px;
                font-size: 12px;
                font-weight: 600;
                color: var(--uwb-text);
                background: #ffffff;
                border: 1px solid var(--uwb-border);
                border-radius: 6px;
                cursor: pointer;
                transition: all 0.2s ease;
            }
            .uwb-btn-mini:hover {
                background: #f1f5f9;
                border-color: #cbd5e1;
            }
            .uwb-btn-mini-danger {
                background: #fee2e2;
                border-color: #fca5a5;
                color: #991b1b;
            }
            .uwb-btn-mini-danger:hover {
                background: #fecaca;
                border-color: #f87171;
            }

            /* Toggle Buttons Caching & Optimization switches */
            .uwb-toggle-container {
                display: inline-flex;
                background: #f1f5f9;
                border: 1px solid #e2e8f0;
                border-radius: 100px;
                padding: 4px;
                gap: 4px;
                align-items: center;
                box-sizing: border-box;
            }
            .uwb-toggle-btn {
                padding: 6px 20px;
                font-size: 13.5px;
                font-weight: 800;
                cursor: pointer;
                border-radius: 100px;
                color: #64748b;
                transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
                user-select: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 60px;
                border: none;
                background: transparent;
                box-sizing: border-box;
                line-height: 1;
            }
            .uwb-toggle-btn.active {
                background: #6366f1; /* Vibrant blue/indigo */
                color: #ffffff !important;
                box-shadow: 0 4px 6px -1px rgba(99, 102, 241, 0.2), 0 2px 4px -1px rgba(99, 102, 241, 0.1);
            }
            .uwb-toggle-btn:hover:not(.active) {
                color: #6366f1;
            }
            .uwb-toggle-container.disabled {
                background: #e2e8f0;
                cursor: not-allowed;
            }
            .uwb-toggle-btn.disabled {
                cursor: not-allowed;
                opacity: 0.6;
            }
            .uwb-toggle-btn.disabled.active {
                background: #94a3b8;
                color: #ffffff !important;
                box-shadow: none;
            }
            .uwb-toggle-input {
                display: none !important;
            }
            /* Content wrapper fade animation */
            .uwb-module-content-wrap {
                animation: uwbFadeIn 0.25s ease;
            }
            @keyframes uwbFadeIn {
                from { opacity: 0; transform: translateY(-6px); }
                to   { opacity: 1; transform: translateY(0); }
            }
        </style>
