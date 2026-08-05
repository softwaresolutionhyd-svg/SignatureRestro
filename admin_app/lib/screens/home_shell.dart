import 'dart:async';

import 'package:flutter/material.dart';
import 'package:intl/intl.dart';
import 'package:provider/provider.dart';

import '../providers/app_state.dart';
import '../services/session.dart';
import 'analytics_screen.dart';
import 'attendance_screen.dart';
import 'bills_screen.dart';
import 'expenses_screen.dart';
import 'kitchen_voids_screen.dart';
import 'reports_screen.dart';

/// Dark module launcher — same vibe as web dashboard, only admin modules.
class HomeShell extends StatefulWidget {
  const HomeShell({super.key});

  @override
  State<HomeShell> createState() => _HomeShellState();
}

class _HomeShellState extends State<HomeShell> {
  late DateTime _now;
  Timer? _clock;

  @override
  void initState() {
    super.initState();
    _now = DateTime.now();
    _clock = Timer.periodic(const Duration(seconds: 30), (_) {
      if (mounted) setState(() => _now = DateTime.now());
    });
    WidgetsBinding.instance.addPostFrameCallback((_) {
      context.read<AppState>().refreshDashboard();
    });
  }

  @override
  void dispose() {
    _clock?.cancel();
    super.dispose();
  }

  String _greet() {
    final h = _now.hour;
    if (h < 12) return 'Good morning';
    if (h < 18) return 'Good afternoon';
    return 'Good evening';
  }

  Future<void> _open(Widget page, {Future<void> Function()? preload}) async {
    if (preload != null) await preload();
    if (!mounted) return;
    await Navigator.of(context).push(MaterialPageRoute(builder: (_) => page));
  }

  @override
  Widget build(BuildContext context) {
    final session = context.watch<Session>();
    final time = DateFormat('HH:mm').format(_now);
    final date = DateFormat('EEEE, d MMMM').format(_now);

    final modules = <_ModuleTile>[
      _ModuleTile(
        label: 'Analytics',
        color: const Color(0xFF7C3AED),
        icon: Icons.bar_chart_rounded,
        onTap: () => _open(const AnalyticsScreen(), preload: () => context.read<AppState>().refreshAnalytics()),
      ),
      _ModuleTile(
        label: 'Pending / Paid Bills',
        color: const Color(0xFF0D9488),
        icon: Icons.receipt_long_rounded,
        onTap: () => _open(const BillsScreen(), preload: () => context.read<AppState>().refreshBills()),
      ),
      _ModuleTile(
        label: 'Cancel Orders',
        color: const Color(0xFFEA580C),
        icon: Icons.cancel_outlined,
        onTap: () => _open(const KitchenVoidsPage(), preload: () => context.read<AppState>().refreshVoids()),
      ),
      _ModuleTile(
        label: 'Expense',
        color: const Color(0xFF14B8A6),
        icon: Icons.money_off_csred_rounded,
        onTap: () => _open(const ExpensesPage(), preload: () => context.read<AppState>().refreshExpenses()),
      ),
      _ModuleTile(
        label: 'Attendance',
        color: const Color(0xFFEC4899),
        icon: Icons.groups_rounded,
        onTap: () => _open(const AttendanceScreen(), preload: () => context.read<AppState>().refreshAttendance()),
      ),
      _ModuleTile(
        label: 'Reports',
        color: const Color(0xFFB45309),
        icon: Icons.insights_rounded,
        onTap: () => _open(const ReportsScreen(), preload: () => context.read<AppState>().refreshDashboard()),
      ),
    ];

    return Scaffold(
      backgroundColor: const Color(0xFF0B1220),
      body: SafeArea(
        child: RefreshIndicator(
          color: const Color(0xFF7C3AED),
          onRefresh: () => context.read<AppState>().refreshDashboard(),
          child: CustomScrollView(
            physics: const AlwaysScrollableScrollPhysics(),
            slivers: [
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(20, 12, 12, 0),
                  child: Row(
                    children: [
                      Container(
                        width: 36,
                        height: 36,
                        decoration: BoxDecoration(
                          borderRadius: BorderRadius.circular(10),
                        ),
                        clipBehavior: Clip.antiAlias,
                        child: Image.asset('assets/stair_icon.png', fit: BoxFit.cover),
                      ),
                      const Spacer(),
                      IconButton(
                        tooltip: 'Logout',
                        onPressed: () => session.logout(),
                        icon: const Icon(Icons.logout_rounded, color: Colors.white70),
                      ),
                    ],
                  ),
                ),
              ),
              SliverToBoxAdapter(
                child: Padding(
                  padding: const EdgeInsets.fromLTRB(24, 28, 24, 8),
                  child: Column(
                    children: [
                      Text(
                        time,
                        style: const TextStyle(
                          fontSize: 64,
                          fontWeight: FontWeight.w300,
                          color: Colors.white,
                          height: 1,
                          letterSpacing: -1,
                        ),
                      ),
                      const SizedBox(height: 6),
                      Text(date, style: TextStyle(color: Colors.white.withOpacity(0.55), fontSize: 15)),
                      const SizedBox(height: 18),
                      Wrap(
                        alignment: WrapAlignment.center,
                        crossAxisAlignment: WrapCrossAlignment.center,
                        spacing: 8,
                        runSpacing: 8,
                        children: [
                          Text(
                            '${_greet()}, ',
                            style: TextStyle(color: Colors.white.withOpacity(0.75), fontSize: 15),
                          ),
                          Text(
                            session.userName.isEmpty ? 'Admin' : session.userName,
                            style: const TextStyle(color: Colors.white, fontWeight: FontWeight.w700, fontSize: 15),
                          ),
                          if (session.userRole.isNotEmpty)
                            Container(
                              padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
                              decoration: BoxDecoration(
                                color: Colors.white.withOpacity(0.08),
                                borderRadius: BorderRadius.circular(20),
                                border: Border.all(color: Colors.white12),
                              ),
                              child: Text(
                                session.userRole,
                                style: TextStyle(color: Colors.white.withOpacity(0.8), fontSize: 12),
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
              SliverPadding(
                padding: const EdgeInsets.fromLTRB(20, 28, 20, 32),
                sliver: SliverGrid(
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 3,
                    mainAxisSpacing: 18,
                    crossAxisSpacing: 12,
                    childAspectRatio: 0.82,
                  ),
                  delegate: SliverChildBuilderDelegate(
                    (context, i) => _ModuleCard(tile: modules[i]),
                    childCount: modules.length,
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}

class _ModuleTile {
  const _ModuleTile({
    required this.label,
    required this.color,
    required this.icon,
    required this.onTap,
  });

  final String label;
  final Color color;
  final IconData icon;
  final VoidCallback onTap;
}

class _ModuleCard extends StatelessWidget {
  const _ModuleCard({required this.tile});

  final _ModuleTile tile;

  @override
  Widget build(BuildContext context) {
    return InkWell(
      onTap: tile.onTap,
      borderRadius: BorderRadius.circular(18),
      child: Column(
        children: [
          Expanded(
            child: Container(
              width: double.infinity,
              decoration: BoxDecoration(
                color: const Color(0xFF151C2C),
                borderRadius: BorderRadius.circular(18),
                border: Border.all(color: Colors.white.withOpacity(0.06)),
                boxShadow: [
                  BoxShadow(
                    color: tile.color.withOpacity(0.18),
                    blurRadius: 18,
                    offset: const Offset(0, 8),
                  ),
                ],
              ),
              child: Icon(tile.icon, size: 34, color: tile.color),
            ),
          ),
          const SizedBox(height: 8),
          Text(
            tile.label,
            textAlign: TextAlign.center,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: const TextStyle(color: Colors.white, fontSize: 12, fontWeight: FontWeight.w500, height: 1.2),
          ),
        ],
      ),
    );
  }
}

/// Scaffold wrapper so list screens match dark theme.
class AdminDarkScaffold extends StatelessWidget {
  const AdminDarkScaffold({super.key, required this.title, required this.body, this.actions});

  final String title;
  final Widget body;
  final List<Widget>? actions;

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: const Color(0xFF0B1220),
      appBar: AppBar(
        backgroundColor: const Color(0xFF0B1220),
        foregroundColor: Colors.white,
        elevation: 0,
        title: Text(title),
        actions: actions,
      ),
      body: body,
    );
  }
}

class KitchenVoidsPage extends StatelessWidget {
  const KitchenVoidsPage({super.key});

  @override
  Widget build(BuildContext context) {
    return const AdminDarkScaffold(
      title: 'Cancel Orders',
      body: KitchenVoidsScreen(),
    );
  }
}

class ExpensesPage extends StatelessWidget {
  const ExpensesPage({super.key});

  @override
  Widget build(BuildContext context) {
    return const AdminDarkScaffold(
      title: 'Expense',
      body: ExpensesScreen(),
    );
  }
}
