import 'package:flutter/material.dart';
import 'package:alertas_ciudadanas/views/home_screen.dart';
import 'package:alertas_ciudadanas/views/map_screen.dart';
import 'package:alertas_ciudadanas/views/recent_activity_screen.dart';
import 'package:alertas_ciudadanas/views/profile_page.dart';
import 'package:alertas_ciudadanas/models/alert_model.dart';
import 'package:provider/provider.dart';
import 'package:alertas_ciudadanas/viewmodels/alert_viewmodel.dart';
import 'package:alertas_ciudadanas/viewmodels/auth_viewmodel.dart';
import 'package:alertas_ciudadanas/core/utils/role_utils.dart';
import 'package:alertas_ciudadanas/core/network/dio_client.dart';
import 'package:alertas_ciudadanas/services/fcm_service.dart';
import 'package:alertas_ciudadanas/services/ambulance_sharing.dart';
import 'package:alertas_ciudadanas/views/case_tracking_screen.dart';
import 'package:alertas_ciudadanas/views/alert_detail_screen.dart';
import 'package:alertas_ciudadanas/views/missing_cases_screen.dart';

class MainNavigationScreen extends StatefulWidget {
  static final GlobalKey<MainNavigationScreenState> navigationKey = GlobalKey<MainNavigationScreenState>();
  const MainNavigationScreen({super.key});

  @override
  State<MainNavigationScreen> createState() => MainNavigationScreenState();
}

class MainNavigationScreenState extends State<MainNavigationScreen> {
  int currentIndex = 0;
  AlertModel? selectedAlert;
  bool traceRouteOnMap = false;
  int? selectedDispatchId;
  final _fcm = FcmService();
  String? _ambulanceSharingUser;
  int? _openingDispatchId;
  
  final GlobalKey<MapScreenState> _mapKey = GlobalKey<MapScreenState>();
  final GlobalKey<RecentActivityScreenState> _recentActivityKey = GlobalKey<RecentActivityScreenState>();
  final GlobalKey<ProfilePageState> _profilePageKey = GlobalKey<ProfilePageState>();

  late PageController _pageController;

  @override
  void initState() {
    super.initState();
    _pageController = PageController(initialPage: currentIndex);
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (!mounted) return;
      final auth = context.read<AuthViewModel>();
      final alerts = context.read<AlertViewModel>();
      alerts.fetchAlerts(
            includeDeleted: isStaffRole(auth.user?.roleId, auth.user?.roleName),
          );
      final user = auth.user;
      if (user != null) {
        if (!isStaffRole(user.roleId, user.roleName)) {
          _ambulanceSharingUser = user.id;
          AmbulanceSharing.instance.attach(user.id);
        }
        _fcm.init(
          user.id,
          onAssignmentOpened: _openAssignment,
          onCaseOpened: _openCase,
          onReportOpened: _openReport,
          onMissingCaseOpened: _openMissingCase,
          onForegroundReport: () => alerts.fetchAlerts(
            includeDeleted: isStaffRole(user.roleId, user.roleName),
          ),
        );
      }
    });
  }

  @override
  void dispose() {
    AmbulanceSharing.instance.detach(_ambulanceSharingUser);
    _fcm.dispose();
    _pageController.dispose();
    super.dispose();
  }

  void navigateToMap(AlertModel alert, {bool traceRoute = false, int? dispatchId}) {
    setState(() {
      selectedAlert = alert;
      selectedDispatchId = dispatchId;
      traceRouteOnMap = traceRoute;
      currentIndex = 1;
    });
    if (_pageController.hasClients) {
      _pageController.jumpToPage(1);
    }
  }

  Future<void> _openAssignment(int id) async {
    if (!mounted ||
        context.read<AuthViewModel>().user?.authorityProfileType != 'paramedico') {
      return;
    }
    if (_openingDispatchId == id) return;
    _openingDispatchId = id;
    try {
      final response = await dioClient.dio.get('/dispatches/$id/assignment');
      final json = response.data as Map<String, dynamic>;
      final assignment = (json['data'] ?? json) as Map<String, dynamic>;
      final alert = AlertModel.fromJson(assignment['response_report'] as Map<String, dynamic>);
      if (!mounted || _openingDispatchId != id) return;
      Navigator.of(context).popUntil((route) => route.isFirst);
      navigateToMap(alert, dispatchId: id);
      await WidgetsBinding.instance.endOfFrame;
      if (!mounted || _openingDispatchId != id) return;
      await _mapKey.currentState?.showAssignedTrip(id, alert);
    } catch (_) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('El despacho ya no está disponible.')),
      );
    } finally {
      if (_openingDispatchId == id) _openingDispatchId = null;
    }
  }

  /// HU-26: al tocar un push de seguimiento se abre la línea de tiempo del caso.
  void _openCase(int reportId) {
    if (!mounted) return;
    Navigator.of(context).push(
      MaterialPageRoute(builder: (_) => CaseTrackingScreen(reportId: reportId)),
    );
  }

  void _openReport(int reportId) {
    if (!mounted) return;
    Navigator.of(context).push(
      MaterialPageRoute<void>(builder: (_) => AlertDetailScreen(reportId: reportId)),
    );
  }

  void _openMissingCase(int id) {
    if (!mounted) return;
    Navigator.of(context).push<void>(
      MaterialPageRoute(builder: (_) => MissingCaseDetailScreen(caseId: id)),
    );
  }

  void selectTab(int index) {
    setState(() {
      currentIndex = index;
    });
    if (_pageController.hasClients) {
      _pageController.jumpToPage(index);
    }
  }

  Widget _buildNavItem(int index, IconData activeIcon, IconData inactiveIcon, String label, {bool isCoffee = false}) {
    final isSelected = currentIndex == index;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final Color itemColor;
    if (isCoffee) {
      itemColor = isSelected ? const Color(0xFFB45F4B) : const Color(0xFFB45F4B).withValues(alpha: 0.65);
    } else {
      itemColor = isSelected
          ? (isDark ? Colors.white : Colors.black87)
          : (isDark ? Colors.white.withValues(alpha: 0.38) : Colors.black.withValues(alpha: 0.38));
    }

    return Expanded(
      child: InkWell(
        onTap: () => selectTab(index),
        splashColor: Colors.transparent,
        highlightColor: Colors.transparent,
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              isSelected ? activeIcon : inactiveIcon,
              color: itemColor,
              size: isSelected ? 23 : 22,
            ),
            const SizedBox(height: 4),
            Text(
              label,
              style: TextStyle(
                color: itemColor,
                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                fontSize: isSelected ? 11.5 : 11,
                letterSpacing: 0.4,
              ),
            ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      // SE ELIMINÓ EL SAFEAREA DE AQUÍ PARA PERMITIR QUE LAS PESTAÑAS USEN TODO EL ESPACIO DE LA PANTALLA
      body: PageView(
        controller: _pageController,
        physics: const NeverScrollableScrollPhysics(),
        onPageChanged: (index) {
          if (index == 1) _mapKey.currentState?.reload();
          if (index == 2) _recentActivityKey.currentState?.reload();
          if (index == 3) _profilePageKey.currentState?.reload();
          setState(() {
            currentIndex = index;
            if (index != 1) {
              traceRouteOnMap = false;
            }
          });
        },
        children: [
          const HomeScreen(), // Ya maneja internamente su propio espaciado superior manual
          MapScreen(
            key: _mapKey,
            initialAlert: selectedAlert,
            assignedDispatchId: selectedDispatchId,
            shouldTraceRoute: traceRouteOnMap,
          ),
          RecentActivityScreen(
            key: _recentActivityKey,
            onAlertTap: (alert, {bool traceRoute = false}) {
              navigateToMap(alert, traceRoute: traceRoute);
            },
          ),
          ProfilePage(
            key: _profilePageKey,
            onAlertTap: navigateToMap,
          ),
        ],
      ),
      bottomNavigationBar: Container(
        height: 60 + MediaQuery.paddingOf(context).bottom,
        decoration: BoxDecoration(
          color: Theme.of(context).brightness == Brightness.dark
              ? const Color(0xFF262624)
              : Colors.white,
          border: Border(
            top: BorderSide(
              color: Theme.of(context).brightness == Brightness.dark
                  ? Colors.white.withValues(alpha: 0.12)
                  : Colors.black.withValues(alpha: 0.12),
              width: 0.5,
            ),
          ),
        ),
        padding: EdgeInsets.only(bottom: MediaQuery.paddingOf(context).bottom),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceAround,
          children: [
            _buildNavItem(0, Icons.home_rounded, Icons.home_outlined, 'Inicio', isCoffee: true),
            _buildNavItem(1, Icons.map_rounded, Icons.map_outlined, 'Mapa'),
            _buildNavItem(2, Icons.question_answer_rounded, Icons.question_answer_rounded, 'Alertas'),
            _buildNavItem(3, Icons.person_rounded, Icons.person_outline_rounded, 'Perfil'),
          ],
        ),
      ),
    );
  }
}
