
import Swal from 'sweetalert2';
import withReactContent from 'sweetalert2-react-content';

const MySwal = withReactContent(Swal);

/**
 * Show a success toast notification.
 * @param {string} title - The message to display.
 */
export const showSuccessToast = ( title ) => {
    MySwal.fire({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        icon: 'success',
        title: title,
        didOpen: (toast) => {
            toast.style.marginTop = '45px';
            const titleEl = toast.querySelector('.swal2-title');
            if (titleEl) {
                titleEl.style.setProperty('font-size', '13px', 'important');
                titleEl.style.setProperty('line-height', '1.4', 'important');
            }
            const icon = toast.querySelector('.swal2-icon');
            if (icon) {
                icon.style.transform = 'scale(0.7)';
                icon.style.margin = '0 10px 0 0';
            }
        }
    });
};

/**
 * Show an error alert.
 * @param {string} title - The title of the error.
 * @param {string} text - The error message.
 */
export const showErrorAlert = ( title, text ) => {
    MySwal.fire( title, text, 'error' );
};

export const showConfirmDialog = async ( title, text, confirmText, icon = 'warning' ) => {
    return MySwal.fire({
        title: title,
        text: text,
        icon: icon,
        showCancelButton: true,
        confirmButtonColor: '#d63638',
        confirmButtonText: confirmText
    });
};

export const showInputDialog = async ( title, text, placeholder ) => {
    return MySwal.fire({
        title: title,
        text: text,
        input: 'url',
        inputPlaceholder: placeholder,
        showCancelButton: true,
        confirmButtonText: 'Install',
        confirmButtonColor: '#d63638',
        inputValidator: (value) => {
            if (!value) {
                return 'You need to write something!'
            }
        }
    });
};

export const showUploadDialog = async ( title, text ) => {
    return MySwal.fire({
        title: title,
        text: text,
        input: 'file',
        inputAttributes: {
            'accept': '.zip',
            'aria-label': 'Upload your plugin zip'
        },
        showCancelButton: true,
        confirmButtonText: 'Install',
        confirmButtonColor: '#d63638',
        inputValidator: (value) => {
            if (!value) {
                return 'You need to select a file!'
            }
        }
    });
};

export const showReinstallDialog = async ( item, defaultUrl = '' ) => {
    // HTML for the dialog
    const htmlContent = `
        <div style="text-align: left;">
            <p style="margin-bottom: 15px;">Choose how you want to reinstall <strong>${item.name}</strong>:</p>
            
            <div style="margin-bottom: 15px;">
                <label style="display: block; margin-bottom: 8px; cursor: pointer;">
                    <input type="radio" name="reinstall_source" value="url" checked> 
                    Download from URL
                </label>
                <div id="wfr-url-input-container" style="margin-left: 20px;">
                    <input id="wfr-reinstall-url" class="swal2-input" placeholder="https://example.com/plugin.zip" value="${defaultUrl}" style="margin: 0; width: 100%; box-sizing: border-box;">
                    ${defaultUrl ? '<small style="color: #2271b1;">Found existing update URL.</small>' : ''}
                </div>
            </div>

            <div style="margin-bottom: 5px;">
                <label style="display: block; margin-bottom: 8px; cursor: pointer;">
                    <input type="radio" name="reinstall_source" value="upload"> 
                    Upload Zip File
                </label>
                <div id="wfr-file-input-container" style="margin-left: 20px; display: none;">
                    <input type="file" id="wfr-reinstall-file" class="swal2-file" accept=".zip" style="width: 100%; box-sizing: border-box;">
                </div>
            </div>
        </div>
    `;

    return MySwal.fire({
        title: 'Reinstall Plugin',
        html: htmlContent,
        showCancelButton: true,
        confirmButtonText: 'Reinstall',
        confirmButtonColor: '#d63638',
        didOpen: () => {
            const popup = MySwal.getPopup();
            const radios = popup.querySelectorAll('input[name="reinstall_source"]');
            const urlContainer = popup.querySelector('#wfr-url-input-container');
            const fileContainer = popup.querySelector('#wfr-file-input-container');

            radios.forEach(radio => {
                radio.addEventListener('change', (e) => {
                    if (e.target.value === 'url') {
                        urlContainer.style.display = 'block';
                        fileContainer.style.display = 'none';
                    } else {
                        urlContainer.style.display = 'none';
                        fileContainer.style.display = 'block';
                    }
                });
            });
        },
        preConfirm: () => {
            const source = MySwal.getPopup().querySelector('input[name="reinstall_source"]:checked').value;
            if ( source === 'url' ) {
                const url = MySwal.getPopup().querySelector('#wfr-reinstall-url').value;
                if ( ! url ) {
                    MySwal.showValidationMessage('Please enter a valid URL');
                    return false;
                }
                return { mode: 'url', url: url };
            } else {
                const fileInput = MySwal.getPopup().querySelector('#wfr-reinstall-file');
                if ( ! fileInput.files.length ) {
                    MySwal.showValidationMessage('Please select a zip file');
                    return false;
                }
                return { mode: 'upload', file: fileInput.files[0] };
            }
        }
    });
};
