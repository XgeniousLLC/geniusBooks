import GuestLayout from '@/layouts/GuestLayout';

export default function Terms() {
    return (
        <GuestLayout title="Terms of Service" subtitle="Xgenious Accounting">
            <div className="space-y-4 text-sm text-gray-600 leading-relaxed max-h-[60vh] overflow-y-auto pr-2">
                <p>By using Xgenious Accounting you agree to these terms. The service is provided free of charge for eligible businesses.</p>
                <h2 className="text-sm font-semibold text-gray-800">1. Your account</h2>
                <p>You are responsible for the accuracy of the data you enter and for keeping your login credentials secure. Each business workspace is isolated; you must only access workspaces you are a member of.</p>
                <h2 className="text-sm font-semibold text-gray-800">2. Acceptable use</h2>
                <p>Do not use the service for unlawful activity, to store malicious content, or to attempt to access another tenant&apos;s data. We may suspend workspaces that violate these terms.</p>
                <h2 className="text-sm font-semibold text-gray-800">3. Your data</h2>
                <p>You retain ownership of your financial data. You may export your data at any time from Business settings. This is not a substitute for professional accounting or tax advice.</p>
                <h2 className="text-sm font-semibold text-gray-800">4. Availability</h2>
                <p>The service is provided on an &ldquo;as is&rdquo; basis without warranties. We aim for high availability but do not guarantee uninterrupted service.</p>
                <h2 className="text-sm font-semibold text-gray-800">5. Changes</h2>
                <p>We may update these terms; continued use after changes constitutes acceptance.</p>
            </div>
        </GuestLayout>
    );
}
