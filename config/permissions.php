<?php

return [
    'catalog' => ['workspace.view', 'workspace.update', 'workspace.archive', 'workspace.manage_members', 'workspace.manage_roles', 'project.view', 'project.create', 'project.update', 'project.archive', 'project.manage_members', 'task.view', 'task.create', 'task.update', 'task.assign', 'task.review', 'task.archive', 'task.comment', 'task.attach', 'calendar.view', 'calendar.create', 'calendar.update', 'calendar.delete', 'plan.view', 'plan.create', 'plan.update', 'plan.approve', 'plan.apply', 'report.view', 'report.create', 'integration.view', 'integration.manage'],
    'defaults' => [
        'Owner' => ['workspace.view', 'workspace.update', 'workspace.archive', 'workspace.manage_members', 'workspace.manage_roles', 'project.view', 'project.create', 'project.update', 'project.archive', 'project.manage_members', 'task.view', 'task.create', 'task.update', 'task.assign', 'task.review', 'task.archive', 'task.comment', 'task.attach', 'calendar.view', 'calendar.create', 'calendar.update', 'calendar.delete', 'plan.view', 'plan.create', 'plan.update', 'plan.approve', 'plan.apply', 'report.view', 'report.create', 'integration.view', 'integration.manage'],
        'Manager' => ['workspace.view', 'workspace.update', 'workspace.manage_members', 'project.view', 'project.create', 'project.update', 'project.archive', 'project.manage_members', 'task.view', 'task.create', 'task.update', 'task.assign', 'task.review', 'task.archive', 'task.comment', 'task.attach', 'calendar.view', 'calendar.create', 'calendar.update', 'calendar.delete', 'plan.view', 'plan.create', 'plan.update', 'plan.approve', 'plan.apply', 'report.view', 'report.create', 'integration.view'],
        'Member' => ['workspace.view', 'project.view', 'task.view', 'calendar.view', 'plan.view', 'report.view', 'task.create', 'task.update', 'task.comment', 'task.attach', 'calendar.create', 'calendar.update', 'plan.create'],
        'Viewer' => ['workspace.view', 'project.view', 'task.view', 'calendar.view', 'plan.view', 'report.view'],
    ],
];
