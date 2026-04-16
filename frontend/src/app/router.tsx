import { Navigate, Route, Routes } from 'react-router-dom';
import { AppShell } from '@/shared/components/AppShell';
import { HomePage } from '@/features/home/HomePage';
import { LoginPage } from '@/features/auth/pages/LoginPage';
import { RegisterPage } from '@/features/auth/pages/RegisterPage';
import { ParticipantEventListPage } from '@/features/participant/pages/ParticipantEventListPage';
import { ParticipantEventDetailPage } from '@/features/participant/pages/ParticipantEventDetailPage';
import { MyEventsPage } from '@/features/participant/pages/MyEventsPage';
import { OrganizerDashboardPage } from '@/features/organizer/pages/OrganizerDashboardPage';
import { OrganizerEventFormPage } from '@/features/organizer/pages/OrganizerEventFormPage';
import { OrganizerEventRegistrationsPage } from '@/features/organizer/pages/OrganizerEventRegistrationsPage';
import { RequireAuth, RequireRole } from './guards';

export function AppRouter() {
  return (
    <Routes>
      <Route element={<AppShell />}>
        <Route path="/" element={<HomePage />} />
        <Route path="/login" element={<LoginPage />} />
        <Route path="/register" element={<RegisterPage />} />

        <Route element={<RequireAuth />}>
          <Route element={<RequireRole role="participant" />}>
            <Route path="/participant/events" element={<ParticipantEventListPage />} />
            <Route path="/participant/events/:id" element={<ParticipantEventDetailPage />} />
            <Route path="/participant/my-events" element={<MyEventsPage />} />
          </Route>

          <Route element={<RequireRole role="organizer" />}>
            <Route path="/organizer" element={<OrganizerDashboardPage />} />
            <Route path="/organizer/events/new" element={<OrganizerEventFormPage mode="create" />} />
            <Route path="/organizer/events/:id/registrations" element={<OrganizerEventRegistrationsPage />} />
            <Route path="/organizer/events/:id/edit" element={<OrganizerEventFormPage mode="edit" />} />
          </Route>
        </Route>

        <Route path="*" element={<Navigate to="/" replace />} />
      </Route>
    </Routes>
  );
}
