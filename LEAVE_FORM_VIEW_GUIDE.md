# Leave Application Form View & PDF Download Guide

## Overview
This feature allows users and HODs to view leave applications in a formal form format (matching the institutional format) and download them as PDF documents with attachments included.

## Features

### 1. Form-Style View
- **File**: `modules/view_leave_form.php`
- Displays leave application in a formal institutional format
- Matches the paper form layout used by the institution
- Shows all application details including:
  - Employee information (name, designation, department)
  - Leave type and duration
  - Dates (from/to)
  - Reason for leave
  - Mode of transport (if applicable)
  - Work adjustment details (if applicable)
  - Attachment information (if any)
  - Approval history with status
  - Signature sections for HOD and Director

### 2. PDF Download with Attachments
- **File**: `modules/download_leave_pdf.php`
- Generates a comprehensive PDF document containing:
  - **First Page**: Leave application form with all details
  - **Subsequent Pages**: Attached documents (if any)
- Supported attachment types:
  - **PDF files**: Embedded directly into the PDF
  - **Images** (JPG, JPEG, PNG, GIF): Displayed as images in the PDF
  - **Other files**: Reference page with file information

### 3. Integration Points

#### View Application Page (`modules/view_application.php`)
Added buttons:
- **View Form**: Opens the form-style view in a new tab
- **Download PDF**: Downloads the complete PDF with attachments

#### View Leave Page (`modules/view_leave.php`)
Added buttons:
- **View Form**: Opens the form-style view in a new tab
- **Download PDF**: Downloads the complete PDF with attachments

#### Leave Approvals Page (`modules/leave_approvals.php`)
Added buttons in the view modal:
- **View Form**: Opens the form-style view in a new tab
- **Download PDF**: Downloads the complete PDF with attachments

## Usage

### For Staff Members
1. Navigate to "My Leaves" section
2. Click on any leave application to view details
3. Click "View Form" to see the institutional format
4. Click "Download PDF" to get a complete PDF with attachments

### For HODs and Approvers
1. Navigate to "Leave Approvals" section
2. Click the eye icon to view application details
3. In the modal, click "View Form" or "Download PDF"
4. Review the application in formal format
5. Approve or reject as needed

### Print Functionality
- The form view page includes a "Print" button
- Optimized for printing with proper page breaks
- Hides navigation elements when printing

## Technical Details

### PDF Generation
- Uses TCPDF library (included via Composer)
- Generates professional-looking PDFs
- Handles multiple page attachments
- Maintains proper formatting and layout

### File Handling
- Attachments are stored in `uploads/documents/`
- PDF supports embedding of PDF and image files
- Other file types show reference information

### Security
- Session-based authentication required
- Permission checks based on user role
- Only authorized users can view/download applications

## Styling
- Form matches institutional format
- Professional appearance for official use
- Print-friendly layout
- Responsive design for different screen sizes

## Future Enhancements
- Digital signature integration
- QR code for verification
- Watermark for approved/rejected applications
- Email PDF directly to stakeholders
