import * as React from "react";
import { type Icon } from "@tabler/icons-react";

import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar";
import {
  Command,
  CommandDialog,
  CommandEmpty,
  CommandGroup,
  CommandInput,
  CommandItem,
  CommandList,
} from "@/components/ui/command";
import { useNavigate } from "react-router-dom";

export function NavSecondary({
  items,
  ...props
}: {
  items: {
    title: string;
    url: string;
    icon: Icon;
    external?: boolean;
  }[];
} & React.ComponentPropsWithoutRef<typeof SidebarGroup>) {
  const navigate = useNavigate();

  const [open, setOpen] = React.useState(false);

  // Toggle the menu when ⌘K is pressed
  React.useEffect(() => {
    const down = (e: KeyboardEvent) => {
      if (e.key === "k" && (e.metaKey || e.ctrlKey)) {
        e.preventDefault();
        setOpen((open) => !open);
      }
    };

    document.addEventListener("keydown", down);
    return () => document.removeEventListener("keydown", down);
  }, []);

  const itemsNoSearch = items.filter(
    (item) => item.title.toLowerCase() !== "search",
  );
  const searchItem = items.find(
    (item) => item.title.toLowerCase() === "search",
  );

  return (
    <SidebarGroup {...props}>
      <SidebarGroupContent>
        <SidebarMenu>
          {itemsNoSearch.map((item) => (
            <SidebarMenuItem key={item.title}>
              <SidebarMenuButton asChild>
                <a
                  href={item.url}
                  target={item.external ? "_blank" : "_self"}
                  rel={item.external ? "noopener noreferrer" : undefined}
                >
                  <item.icon />
                  <span>{item.title}</span>
                </a>
              </SidebarMenuButton>
            </SidebarMenuItem>
          ))}

          {searchItem && (
            <SidebarMenuItem key={searchItem.title}>
              <SidebarMenuButton asChild>
                <a
                  href={searchItem.url}
                  onClick={(e) => {
                    e.preventDefault();
                    setOpen(true);
                  }}
                >
                  <searchItem.icon />
                  <span>{searchItem.title}</span>
                </a>
              </SidebarMenuButton>
              <CommandDialog open={open} onOpenChange={setOpen}>
                <Command>
                  <CommandInput placeholder="Search for a resource..." />
                  <CommandList>
                    <CommandEmpty>No results found.</CommandEmpty>
                    <CommandGroup heading="Modules">
                      <CommandItem
                        onSelect={() => {
                          setOpen(false);
                          navigate("/");
                        }}
                      >
                        Dashboard
                      </CommandItem>
                      <CommandItem
                        onSelect={() => {
                          setOpen(false);
                          navigate("/sources");
                        }}
                      >
                        Sources
                      </CommandItem>
                      <CommandItem
                        onSelect={() => {
                          setOpen(false);
                          navigate("/logs");
                        }}
                      >
                        Logs
                      </CommandItem>
                    </CommandGroup>
                    <CommandGroup heading="User">
                      <CommandItem
                        onSelect={() => {
                          setOpen(false);
                          navigate("/account");
                        }}
                      >
                        Account
                      </CommandItem>
                      <CommandItem
                        onSelect={() => {
                          setOpen(false);
                          navigate("/audit");
                        }}
                      >
                        Audit
                      </CommandItem>
                    </CommandGroup>
                  </CommandList>
                </Command>
              </CommandDialog>
            </SidebarMenuItem>
          )}
        </SidebarMenu>
      </SidebarGroupContent>
    </SidebarGroup>
  );
}
