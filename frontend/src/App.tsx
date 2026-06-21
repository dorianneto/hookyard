import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { AuthProvider } from "./contexts/AuthContext";
import ProtectedRoute from "./components/ProtectedRoute";
import Layout from "./components/Layout";
import LoginPage from "./pages/LoginPage";
import RegisterPage from "./pages/RegisterPage";
import StripeSuccessPage from "./pages/StripeSuccessPage";
import StripeCancelPage from "./pages/StripeCancelPage";
import RegisterPendingPage from "./pages/RegisterPendingPage";
import DashboardPage from "./pages/DashboardPage";
import SourcesPage from "./pages/SourcesPage";
import NewSourcePage from "./pages/NewSourcePage";
import SourceDetailPage from "./pages/SourceDetailPage";
import NewEndpointPage from "./pages/NewEndpointPage";
import EventDetailPage from "./pages/EventDetailPage";
import AccountPage from "./pages/AccountPage";
import LogsPage from "./pages/LogsPage";
import AuditPage from "./pages/AuditPage";
import SubscriptionPage from "./pages/SubscriptionPage";

function App() {
  return (
    <BrowserRouter>
      <AuthProvider>
        <Routes>
          <Route path="/login" element={<LoginPage />} />
          <Route path="/register" element={<RegisterPage />} />
          <Route path="/register/success" element={<StripeSuccessPage />} />
          <Route path="/register/cancel" element={<StripeCancelPage />} />
          <Route path="/register/pending" element={<RegisterPendingPage />} />
          <Route
            path="/"
            element={
              <ProtectedRoute>
                <Layout>
                  <DashboardPage />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/logs"
            element={
              <ProtectedRoute>
                <Layout>
                  <LogsPage />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/sources"
            element={
              <ProtectedRoute>
                <Layout>
                  <SourcesPage />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/sources/new"
            element={
              <ProtectedRoute>
                <Layout>
                  <NewSourcePage />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/sources/:sourceId/endpoints/new"
            element={
              <ProtectedRoute>
                <Layout>
                  <NewEndpointPage />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/sources/:sourceId/events/:eventId"
            element={
              <ProtectedRoute>
                <Layout>
                  <EventDetailPage />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/sources/:sourceId"
            element={
              <ProtectedRoute>
                <Layout>
                  <SourceDetailPage />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/account"
            element={
              <ProtectedRoute>
                <Layout>
                  <AccountPage />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/audit"
            element={
              <ProtectedRoute>
                <Layout>
                  <AuditPage />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route
            path="/subscription"
            element={
              <ProtectedRoute>
                <Layout>
                  <SubscriptionPage />
                </Layout>
              </ProtectedRoute>
            }
          />
          <Route path="*" element={<Navigate to="/" replace />} />
        </Routes>
      </AuthProvider>
    </BrowserRouter>
  );
}

export default App;
